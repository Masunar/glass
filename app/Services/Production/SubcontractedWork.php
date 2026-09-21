<?php

declare(strict_types=1);

namespace App\Services\Production;

use App\Models\Process;
use App\Enum\ProductionStatus;
use App\Models\ProductionTask;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Etapy wykonywane poza zakładem.
 *
 * `processes.is_subcontracted` istniał od początku i nic go nie
 * czytało. Tutaj dostaje znaczenie: takiego etapu **hala nie odhacza**,
 * bo praca dzieje się gdzie indziej. Zamyka go powrót od podwykonawcy.
 *
 * Zadanie mimo to istnieje i liczy się do `ProductionPlan::allDone()`,
 * więc zlecenie nie przejdzie na „Gotowe", dopóki szkło nie wróci.
 * To jest cała pointa sprzężenia: w starym systemie zlecenie 16492
 * miało status „Gotowe" przy szkle, którego nie było.
 */
final readonly class SubcontractedWork
{
    /**
     * Zamknięcie etapów podzlecanych dla pozycji.
     *
     * Wywoływane, gdy dla pozycji nie zostało już nic w kolejce ani
     * u podwykonawcy. **Nie przy pierwszym powrocie**: formatka na
     * dwadzieścia cztery sztuki, z której trzy się stłukły, wraca
     * w części, a zadanie jest jedno na całą pozycję. Zamknięcie go
     * wcześniej pokazywałoby „hartowanie zrobione" przy trzech szybach
     * nadal w piecu.
     *
     * @return int liczba zamkniętych zadań
     */
    public function close(int $orderItemId): int
    {
        $closed = 0;

        foreach ($this->openTasks($orderItemId) as $task) {
            $task->status = ProductionStatus::DONE;
            $task->finished_at = Carbon::now();
            $task->done_by = Auth::id();
            $task->save();

            $closed++;
        }

        return $closed;
    }

    /**
     * Ponowne otwarcie etapu, gdy praca wraca do podwykonawcy.
     *
     * Stłuczka po zamkniętym etapie znaczy, że szkło jedzie do pieca
     * jeszcze raz — a etap „zrobiony" przy szybie, której nie ma, to
     * dokładnie ten fałsz, przed którym to sprzężenie ma chronić.
     *
     * @return int liczba otwartych zadań
     */
    public function reopen(int $orderItemId): int
    {
        $opened = 0;

        /** @var iterable<ProductionTask> $tasks */
        $tasks = ProductionTask::query()
            ->where('order_item_id', $orderItemId)
            ->where('status', ProductionStatus::DONE->value)
            ->whereIn('process_id', $this->subcontractedProcessIds())
            ->get();

        foreach ($tasks as $task) {
            $task->status = ProductionStatus::PENDING;
            $task->finished_at = null;
            $task->done_by = null;
            $task->save();

            $opened++;
        }

        return $opened;
    }

    /**
     * @return iterable<ProductionTask>
     */
    private function openTasks(int $orderItemId): iterable
    {
        /** @var iterable<ProductionTask> */
        return ProductionTask::query()
            ->where('order_item_id', $orderItemId)
            ->where('status', '!=', ProductionStatus::DONE->value)
            ->whereIn('process_id', $this->subcontractedProcessIds())
            ->get();
    }

    /**
     * @return list<int>
     */
    private function subcontractedProcessIds(): array
    {
        /** @var list<int> */
        return Process::query()
            ->where('is_subcontracted', true)
            ->pluck('id')
            ->all();
    }
}
