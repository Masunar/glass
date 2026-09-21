<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Enum\Permission;
use App\Models\Vehicle;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Salvon\Enum\SubPermission;
use App\Models\TemperingItem;
use Illuminate\Http\JsonResponse;
use App\Models\TemperingBatch;
use Salvon\Controller\ApiController;
use App\Services\Tempering\TemperingBoard;
use App\Services\Tempering\TemperingBatchService;

/**
 * Hartownia: kolejka, partie i powroty.
 */
class TemperingController extends ApiController
{
    public function __construct(
        private readonly TemperingBoard $board,
        private readonly TemperingBatchService $service,
    ) {
        $this->protect(
            ['queue', 'batches', 'show'],
            Permission::TEMPERING->value,
            SubPermission::LIST->value,
        );
        $this->protect(
            ['store', 'addItems', 'plan', 'send', 'receive', 'settle', 'cancel'],
            Permission::TEMPERING->value,
            SubPermission::UPDATE->value,
        );
        $this->protect(
            ['removeItem'],
            Permission::TEMPERING->value,
            SubPermission::DELETE->value,
        );
    }

    public function queue(Request $request): JsonResponse
    {
        return $this->secure(function () use ($request): JsonResponse {
            $thickness = $request->query('thickness');

            return $this->dataResponse($this->board->queue(
                $thickness === null || $thickness === '' ? null : (float) $thickness,
            ));
        });
    }

    public function batches(Request $request): JsonResponse
    {
        return $this->secure(fn(): JsonResponse => $this->dataResponse($this->board->batches(
            $request->query('status') === null ? null : (string) $request->query('status'),
        )));
    }

    public function show(int $batch): JsonResponse
    {
        return $this->secure(fn(): JsonResponse => $this->dataResponse(
            $this->board->card($this->batch($batch)),
        ));
    }

    /**
     * Nowa partia z zaznaczonych pozycji kolejki.
     *
     * Pominięte wracają w odpowiedzi: pozycja, która jest już w innej
     * partii, to szkło leżące fizycznie gdzie indziej, i przeniesienie
     * jej po cichu byłoby zgubieniem tej informacji.
     */
    public function store(Request $request): JsonResponse
    {
        return $this->secure(function () use ($request): JsonResponse {
            /** @var Supplier $supplier */
            $supplier = Supplier::query()->findOrFail((int) $request->input('supplier_id'));

            $batch = $this->service->draft(
                $supplier,
                $this->date($request->input('expected_at')),
                $request->input('note'),
                $this->vehicle($request->input('vehicle_id')),
                $this->date($request->input('departure_at')),
            );

            /** @var list<int> $ids */
            $ids = array_map(intval(...), (array) $request->input('item_ids', []));

            $result = $this->service->add($batch, $ids);

            return $this->dataResponse([
                ...$this->board->card($batch->refresh()),
                'skipped' => $result['skipped'],
            ]);
        });
    }

    /** Zmiana planu kursu: auto, wyjazd, przewidywany powrót. */
    public function plan(Request $request, int $batch): JsonResponse
    {
        return $this->secure(function () use ($request, $batch): JsonResponse {
            $model = $this->service->plan(
                $this->batch($batch),
                $this->vehicle($request->input('vehicle_id')),
                $this->date($request->input('departure_at')),
                $this->date($request->input('expected_at')),
            );

            return $this->dataResponse($this->board->card($model));
        });
    }

    public function addItems(Request $request, int $batch): JsonResponse
    {
        return $this->secure(function () use ($request, $batch): JsonResponse {
            $model = $this->batch($batch);

            /** @var list<int> $ids */
            $ids = array_map(intval(...), (array) $request->input('item_ids', []));

            $result = $this->service->add($model, $ids);

            return $this->dataResponse([
                ...$this->board->card($model->refresh()),
                'skipped' => $result['skipped'],
            ]);
        });
    }

    public function removeItem(int $batch, int $item): JsonResponse
    {
        return $this->secure(function () use ($batch, $item): JsonResponse {
            $model = $this->batch($batch);

            /** @var TemperingItem $position */
            $position = TemperingItem::query()
                ->where('tempering_batch_id', $model->getKey())
                ->findOrFail($item);

            $this->service->remove($model, $position);

            return $this->dataResponse($this->board->card($model->refresh()));
        });
    }

    public function send(Request $request, int $batch): JsonResponse
    {
        return $this->secure(function () use ($request, $batch): JsonResponse {
            $model = $this->service->send(
                $this->batch($batch),
                $this->date($request->input('sent_at')),
            );

            return $this->dataResponse($this->board->card($model));
        });
    }

    /**
     * Powrót partii. `outcomes` to mapa `id pozycji => los`.
     * Pozycja pominięta w mapie traktowana jest jako wrócona.
     */
    public function receive(Request $request, int $batch): JsonResponse
    {
        return $this->secure(function () use ($request, $batch): JsonResponse {
            $model = $this->batch($batch);

            /** @var array<int, string> $outcomes */
            $outcomes = [];

            /** @var array<int|string, mixed> $input */
            $input = (array) $request->input('outcomes', []);

            foreach ($input as $id => $value) {
                $outcomes[(int) $id] = (string) $value;
            }

            $result = $this->service->receive(
                $model,
                $outcomes,
                $this->date($request->input('returned_at')),
                $request->input('note'),
            );

            return $this->dataResponse([
                ...$this->board->card($model->refresh()),
                // Ile pozycji zastepczych wrocilo do kolejki — czlowiek
                // ma to zobaczyc od razu, a nie odkryc jutro.
                'replaced' => count($result['replaced']),
            ]);
        });
    }

    public function settle(Request $request, int $batch): JsonResponse
    {
        return $this->secure(function () use ($request, $batch): JsonResponse {
            $cost = $request->input('net_cost');

            $model = $this->service->settle(
                $this->batch($batch),
                $cost === null || $cost === '' ? null : (string) $cost,
                $request->input('document'),
            );

            return $this->dataResponse($this->board->card($model));
        });
    }

    public function cancel(int $batch): JsonResponse
    {
        return $this->secure(fn(): JsonResponse => $this->dataResponse(
            $this->board->card($this->service->cancel($this->batch($batch))),
        ));
    }

    /** Pusty napis z formularza to brak daty, nie dzisiaj. */
    private function date(mixed $value): ?Carbon
    {
        return $value === null || $value === '' ? null : Carbon::parse((string) $value);
    }

    private function vehicle(mixed $value): ?Vehicle
    {
        if ($value === null || $value === '' || (int) $value === 0) {
            return null;
        }

        /** @var Vehicle|null */
        return Vehicle::query()->find((int) $value);
    }

    private function batch(int $id): TemperingBatch
    {
        /** @var TemperingBatch */
        return TemperingBatch::query()->findOrFail($id);
    }
}
