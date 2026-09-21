<?php

declare(strict_types=1);

namespace App\Services\Tempering;

use Carbon\Carbon;
use RuntimeException;
use App\Models\Supplier;
use App\Models\TemperingItem;
use App\Models\TemperingBatch;
use App\Services\NumberSequence;
use App\Enum\TemperingItemStatus;
use App\Enum\TemperingBatchStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

/**
 * Partia do hartowni: skompletowanie, wysyłka, powrót, rozliczenie.
 *
 * Powrót jest **per pozycja**, a nie per partia, bo z jednego wsadu
 * część wraca, część się tłucze i część wraca do poprawki. Partia,
 * która wraca w całości jednym kliknięciem, opisuje przypadek, który
 * zdarza się najczęściej — ale nie jedyny, jaki trzeba umieć zapisać.
 */
final readonly class TemperingBatchService
{
    public const SEQUENCE = 'tempering_batches';

    public function __construct(
        private TemperingQueue $queue = new TemperingQueue(),
        private NumberSequence $numbers = new NumberSequence(),
    ) {
    }

    public function draft(Supplier $supplier, ?Carbon $expectedAt = null, ?string $note = null): TemperingBatch
    {
        /** @var TemperingBatch */
        return TemperingBatch::query()->create([
            'number' => $this->numbers->next(self::SEQUENCE),
            'supplier_id' => $supplier->id,
            'status' => TemperingBatchStatus::DRAFT->value,
            'expected_at' => $expectedAt,
            'note' => $note,
            'created_by' => Auth::id(),
        ]);
    }

    /**
     * Dopisanie pozycji z kolejki do szkicu partii.
     *
     * @param list<int> $itemIds
     * @return array{added: int, skipped: list<array{id: int, reason: string}>}
     */
    public function add(TemperingBatch $batch, array $itemIds): array
    {
        $this->assertEditable($batch);

        $added = 0;
        $skipped = [];

        /** @var iterable<TemperingItem> $items */
        $items = TemperingItem::query()->whereIn('id', $itemIds)->get();

        foreach ($items as $item) {
            if ($item->tempering_batch_id === (int) $batch->getKey()) {
                continue;
            }

            // Pozycja juz wyslana albo w innej partii nie przenosi sie
            // po cichu — to jest szklo, ktore fizycznie gdzies jest.
            if (!$item->status->isQueued() || $item->tempering_batch_id !== null) {
                $skipped[] = [
                    'id' => (int) $item->getKey(),
                    'reason' => $item->tempering_batch_id !== null
                        ? 'pozycja jest już w innej partii'
                        : 'pozycja nie jest w kolejce (' . $item->status->label() . ')',
                ];

                continue;
            }

            $item->tempering_batch_id = (int) $batch->getKey();
            $item->save();
            $added++;
        }

        return ['added' => $added, 'skipped' => $skipped];
    }

    public function remove(TemperingBatch $batch, TemperingItem $item): void
    {
        $this->assertEditable($batch);

        $item->tempering_batch_id = null;
        $item->save();
    }

    /** Wysyłka: od tej chwili szkło jest poza zakładem. */
    public function send(TemperingBatch $batch, ?Carbon $sentAt = null): TemperingBatch
    {
        if ($batch->status !== TemperingBatchStatus::DRAFT) {
            throw new RuntimeException('Wysłać można tylko szkic partii.');
        }

        if ($batch->items()->count() === 0) {
            throw new RuntimeException('Partia bez pozycji nie ma czego wieźć.');
        }

        return DB::transaction(function () use ($batch, $sentAt): TemperingBatch {
            $batch->status = TemperingBatchStatus::SENT;
            $batch->sent_at = $sentAt ?? Carbon::today();
            $batch->save();

            TemperingItem::query()
                ->where('tempering_batch_id', $batch->getKey())
                ->where('status', TemperingItemStatus::QUEUED->value)
                ->update(['status' => TemperingItemStatus::SENT->value]);

            return $batch->refresh();
        });
    }

    /**
     * Powrót partii: każda pozycja dostaje swój los.
     *
     * Stłuczka i brak zakładają **pozycję zastępczą w kolejce** na tę
     * samą formatkę i tę samą ilość. Klient nie płaci drugi raz — szkło
     * trzeba zrobić od nowa, a to jest koszt, nie sprzedaż.
     *
     * @param array<int, string> $outcomes id pozycji => wartość `TemperingItemStatus`
     * @return array{returned: int, replaced: list<TemperingItem>}
     */
    public function receive(
        TemperingBatch $batch,
        array $outcomes,
        ?Carbon $returnedAt = null,
        ?string $note = null,
    ): array {
        if ($batch->status !== TemperingBatchStatus::SENT) {
            throw new RuntimeException('Wraca tylko partia, która pojechała.');
        }

        return DB::transaction(function () use ($batch, $outcomes, $returnedAt, $note): array {
            $replaced = [];
            $returned = 0;

            /** @var iterable<TemperingItem> $items */
            $items = TemperingItem::query()
                ->where('tempering_batch_id', $batch->getKey())
                ->get();

            foreach ($items as $item) {
                $raw = $outcomes[(int) $item->getKey()] ?? TemperingItemStatus::RETURNED->value;
                $status = TemperingItemStatus::tryFrom($raw) ?? TemperingItemStatus::RETURNED;

                if ($status === TemperingItemStatus::QUEUED || $status === TemperingItemStatus::SENT) {
                    // Pozycja, ktora wrocila, nie moze znow „czekac".
                    $status = TemperingItemStatus::RETURNED;
                }

                $item->status = $status;
                $item->save();

                if ($status->covers()) {
                    $returned++;

                    continue;
                }

                $replaced[] = $this->queue->replace($item);
            }

            $batch->status = TemperingBatchStatus::RETURNED;
            $batch->returned_at = $returnedAt ?? Carbon::today();

            if ($note !== null && $note !== '') {
                $batch->note = $note;
            }

            $batch->save();

            return ['returned' => $returned, 'replaced' => $replaced];
        });
    }

    /**
     * Rozliczenie: koszt partii.
     *
     * **Nie dotyka wyceny zlecenia** — H-08 jest otwarte i nie wiadomo,
     * czy hartowanie idzie do klienta z cennika, czy po koszcie
     * rzeczywistym. Ta sama zasada co przy przyjęciu towaru: liczba ma
     * być widoczna, decyzja należy do człowieka.
     */
    public function settle(TemperingBatch $batch, ?string $netCost, ?string $document = null): TemperingBatch
    {
        if ($batch->status !== TemperingBatchStatus::RETURNED) {
            throw new RuntimeException('Rozlicza się partię, która wróciła.');
        }

        $batch->net_cost = $netCost;
        $batch->document = $document;
        $batch->status = TemperingBatchStatus::SETTLED;
        $batch->save();

        return $batch->refresh();
    }

    /**
     * Anulowanie szkicu. Pozycje wracają do kolejki nietknięte —
     * nigdzie nie pojechały.
     */
    public function cancel(TemperingBatch $batch): TemperingBatch
    {
        if (!$batch->status->isEditable()) {
            throw new RuntimeException('Anulować można tylko szkic partii.');
        }

        return DB::transaction(function () use ($batch): TemperingBatch {
            TemperingItem::query()
                ->where('tempering_batch_id', $batch->getKey())
                ->update(['tempering_batch_id' => null]);

            $batch->status = TemperingBatchStatus::CANCELLED;
            $batch->save();

            return $batch->refresh();
        });
    }

    private function assertEditable(TemperingBatch $batch): void
    {
        if (!$batch->status->isEditable()) {
            throw new RuntimeException('Pozycje zmienia się tylko w szkicu partii.');
        }
    }
}
