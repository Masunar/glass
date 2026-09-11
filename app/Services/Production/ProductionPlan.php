<?php

declare(strict_types=1);

namespace App\Services\Production;

use App\Models\Order;
use App\Models\OrderList;
use App\Models\OrderItem;
use App\Enum\ProductionStatus;
use App\Models\ProductionTask;
use App\Models\OrderItemProcess;
use Illuminate\Support\Facades\DB;

/**
 * Zakładanie i odświeżanie zadań produkcyjnych zlecenia.
 *
 * Zadania powstają z marszruty, czyli z procesów przypisanych do
 * pozycji — jedno zadanie na parę pozycja × proces. Wywoływane przy
 * wejściu zlecenia na produkcję, ale napisane tak, żeby dało się je
 * powtórzyć: drugie wejście ma odnaleźć te same zadania, nie założyć
 * drugi komplet.
 *
 * **Czego nie kasujemy.** Gdy proces zniknie z marszruty, usuwamy tylko
 * zadanie, którego nikt nie ruszył. Etap zaczęty, wykonany albo
 * zgłoszony jako problem to praca, która się wydarzyła — kasowanie jej,
 * bo ktoś poprawił wycenę, byłoby zacieraniem śladu. Taki etap zostaje
 * i człowiek musi go domknąć świadomie.
 *
 * Alternatywy nie wchodzą do produkcji: lista oznaczona jako wariant
 * nie jest tym, co klient zamówił.
 */
final readonly class ProductionPlan
{
    /**
     * @return array{created: int, removed: int}
     */
    public function sync(Order $order): array
    {
        $order->loadMissing(['lists.items.processes']);

        $wanted = [];

        /** @var OrderList $list */
        foreach ($order->lists as $list) {
            if (!$list->is_included) {
                continue;
            }

            /** @var OrderItem $item */
            foreach ($list->items as $item) {
                /** @var OrderItemProcess $entry */
                foreach ($item->processes as $entry) {
                    $wanted[(int) $item->getKey() . ':' . (int) $entry->process_id] = [
                        'item' => $item,
                        'entry' => $entry,
                    ];
                }
            }
        }

        /** @var array<string, ProductionTask> $existing */
        $existing = [];

        foreach ($this->tasksOf($order) as $task) {
            $existing[(int) $task->order_item_id . ':' . (int) $task->process_id] = $task;
        }

        $created = 0;
        $removed = 0;

        DB::transaction(function () use ($order, $wanted, $existing, &$created, &$removed): void {
            foreach ($wanted as $key => $pair) {
                if (isset($existing[$key])) {
                    // Parametr etapu moze sie zmienic przy poprawce
                    // wyceny (inny RAL, inna faza). Dopoki nikt etapu nie
                    // ruszyl, karta operatora ma pokazywac aktualny.
                    $task = $existing[$key];

                    if ($task->status === ProductionStatus::PENDING) {
                        $task->update(['parameter' => $pair['entry']->parameter]);
                    }

                    continue;
                }

                // Proces zawsze istnieje: klucz obcy jest wymagany,
                // a slownik procesow ma `restrictOnDelete`.
                $process = $pair['entry']->process;

                ProductionTask::query()->create([
                    'order_id' => (int) $order->getKey(),
                    'order_item_id' => (int) $pair['item']->getKey(),
                    'process_id' => (int) $pair['entry']->process_id,
                    'workstation_id' => $process->workstation_id,
                    'parameter' => $pair['entry']->parameter,
                    'position' => (int) $process->default_order,
                    'status' => ProductionStatus::PENDING->value,
                ]);

                $created++;
            }

            foreach ($existing as $key => $task) {
                if (isset($wanted[$key]) || $task->status !== ProductionStatus::PENDING) {
                    continue;
                }

                $task->delete();
                $removed++;
            }
        });

        return ['created' => $created, 'removed' => $removed];
    }

    /**
     * Czy wszystkie etapy zlecenia są wykonane.
     *
     * Zlecenie bez ani jednego zadania nie ma czego czekać — pozycja
     * usługowa bez procesów technologicznych jest gotowa w chwili, gdy
     * ktoś ją wykona poza halą.
     */
    public function allDone(Order $order): bool
    {
        return !ProductionTask::query()
            ->where('order_id', (int) $order->getKey())
            ->where('status', '!=', ProductionStatus::DONE->value)
            ->exists();
    }

    /**
     * @return iterable<ProductionTask>
     */
    private function tasksOf(Order $order): iterable
    {
        /** @var iterable<ProductionTask> */
        return ProductionTask::query()
            ->where('order_id', (int) $order->getKey())
            ->get();
    }
}
