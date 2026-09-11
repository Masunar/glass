<?php

declare(strict_types=1);

namespace App\Services\Production;

use Carbon\Carbon;
use App\Models\Order;
use App\Support\Normalize;
use App\Enum\ProductionIssue;
use App\Services\AuditTrail;
use App\Enum\ProductionStatus;
use App\Models\ProductionTask;
use Illuminate\Support\Facades\Auth;

/**
 * Odhaczanie etapów na stanowisku.
 *
 * Każde zdarzenie idzie do dziennika zlecenia, nie do osobnego rejestru
 * produkcji. Pytanie „dlaczego to zlecenie stoi" pada przy zleceniu,
 * więc odpowiedź ma być w jednym miejscu z resztą jego historii.
 *
 * **Czas jest mierzony, nie zakładany.** Słownik procesów ma puste
 * `setup_minutes` i `unit_minutes`, bo nikt ich nigdy nie zmierzył.
 * Zapisujemy więc zegar ścienny między „zacznij" a „wykonane" — z tym,
 * że to naprawdę zegar ścienny: obejmuje przerwę i noc. Do planowania
 * wolno tych liczb użyć dopiero po odfiltrowaniu, i dlatego etap
 * odhaczony bez rozpoczęcia nie dostaje czasu zgadywanego z niczego.
 */
final readonly class TaskExecution
{
    public function __construct(
        private AuditTrail $audit = new AuditTrail(),
    ) {
    }

    /**
     * @return array{errors: array<string, list<string>>, status: string|null}
     */
    public function start(int $taskId): array
    {
        $task = $this->task($taskId);

        if ($task === null) {
            return $this->fail('Takiego etapu nie ma.');
        }

        if ($task->status === ProductionStatus::DONE) {
            return $this->fail('Ten etap jest już wykonany.');
        }

        // Wznowienie po problemie nie przestawia zegara: pierwszy start
        // zostaje, bo to od niego liczy sie, jak dlugo pozycja siedzi
        // na stanowisku.
        $task->update([
            'status' => ProductionStatus::IN_PROGRESS->value,
            'started_at' => $task->started_at ?? Carbon::now(),
        ]);

        $this->log($task, 'etap rozpoczęty', null, $this->label($task));

        return ['errors' => [], 'status' => ProductionStatus::IN_PROGRESS->value];
    }

    /**
     * @return array{errors: array<string, list<string>>, status: string|null}
     */
    public function finish(int $taskId, mixed $note = null): array
    {
        $task = $this->task($taskId);

        if ($task === null) {
            return $this->fail('Takiego etapu nie ma.');
        }

        if ($task->status === ProductionStatus::DONE) {
            return $this->fail('Ten etap jest już wykonany.');
        }

        $now = Carbon::now();
        $started = $task->started_at;

        $task->update([
            'status' => ProductionStatus::DONE->value,
            'finished_at' => $now,
            // Bez rozpoczecia nie ma czego mierzyc. Zero albo domysl
            // zatrulby slownik czasow, ktory dopiero powstaje.
            'minutes_spent' => $started === null ? null : max(0, (int) $started->diffInMinutes($now)),
            'issue_type' => null,
            'note' => Normalize::text(is_string($note) ? $note : null) ?? $task->note,
            'done_by' => Auth::id(),
        ]);

        $this->log($task, 'etap wykonany', null, $this->label($task));

        return ['errors' => [], 'status' => ProductionStatus::DONE->value];
    }

    /**
     * Zgłoszenie problemu ze stanowiska.
     *
     * Powód jest wymagany. „Problem" bez zdania to czerwona kropka,
     * której nikt nie umie odblokować — a właśnie takich rzeczy ten
     * moduł ma nie produkować.
     *
     * @return array{errors: array<string, list<string>>, status: string|null}
     */
    public function reportIssue(int $taskId, mixed $type, mixed $note): array
    {
        $task = $this->task($taskId);

        if ($task === null) {
            return $this->fail('Takiego etapu nie ma.');
        }

        $issue = is_string($type) ? ProductionIssue::tryFrom($type) : null;

        if ($issue === null) {
            return ['errors' => ['issue_type' => ['Wskaż rodzaj problemu.']], 'status' => null];
        }

        $text = Normalize::text(is_string($note) ? $note : null);

        if ($text === null) {
            return ['errors' => ['note' => ['Opisz, co się stało — bez tego nikt tego nie odblokuje.']], 'status' => null];
        }

        $task->update([
            'status' => ProductionStatus::PROBLEM->value,
            'issue_type' => $issue->value,
            'note' => $text,
        ]);

        $this->log($task, 'problem na produkcji', $this->label($task), $text);

        return ['errors' => [], 'status' => ProductionStatus::PROBLEM->value];
    }

    /**
     * Cofnięcie odhaczenia. Terminal ma jeden duży przycisk i pomyłka
     * jest kwestią czasu — ale cofnięcie zostawia ślad w dzienniku,
     * a zmierzony czas przepada, bo przestał cokolwiek znaczyć.
     *
     * @return array{errors: array<string, list<string>>, status: string|null}
     */
    public function reopen(int $taskId): array
    {
        $task = $this->task($taskId);

        if ($task === null) {
            return $this->fail('Takiego etapu nie ma.');
        }

        if ($task->status !== ProductionStatus::DONE) {
            return $this->fail('Ten etap nie jest odhaczony.');
        }

        $task->update([
            'status' => ProductionStatus::PENDING->value,
            'started_at' => null,
            'finished_at' => null,
            'minutes_spent' => null,
            'done_by' => null,
        ]);

        $this->log($task, 'cofnięcie wykonania', $this->label($task), null);

        return ['errors' => [], 'status' => ProductionStatus::PENDING->value];
    }

    private function task(int $taskId): ?ProductionTask
    {
        /** @var ProductionTask|null */
        return ProductionTask::query()->with(['process', 'item'])->find($taskId);
    }

    /** Proces i pozycja są wymagane kluczem obcym, więc zawsze są. */
    private function label(ProductionTask $task): string
    {
        return trim(sprintf('%s — %s', $task->process->name, $task->item->name));
    }

    private function log(ProductionTask $task, string $field, ?string $before, ?string $after): void
    {
        $this->audit->write(
            Order::class,
            (int) $task->order_id,
            [['field' => $field, 'before' => $before, 'after' => $after]],
            'production',
        );
    }

    /**
     * @return array{errors: array<string, list<string>>, status: string|null}
     */
    private function fail(string $message): array
    {
        return ['errors' => ['task' => [$message]], 'status' => null];
    }
}
