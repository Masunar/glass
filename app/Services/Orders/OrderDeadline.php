<?php

declare(strict_types=1);

namespace App\Services\Orders;

use Carbon\Carbon;
use App\Models\Order;
use Carbon\CarbonInterface;
use App\Services\AuditTrail;
use App\Services\Calendar\WorkingDays;

/**
 * Termin klienta wyliczany z pozycji.
 *
 * Uwaga klienta (25.09): system podaje datę sam, człowiek może ją
 * zmienić. Decyzje Marcina:
 *
 * - data = dziś + dni z pozycji (`OrderSchedule::days()`, czyli
 *   najdłuższa formatka), liczone w dniach roboczych (`WorkingDays`);
 * - przeliczana przy zmianie pozycji, **dopóki nikt jej nie ustawił
 *   ręcznie** (`deadline_manual`) — potem system jej nie rusza;
 * - tylko przed przekazaniem na produkcję: po przekazaniu klient już
 *   termin zna, a hala układa według niego kolejkę.
 *
 * Data przesuwa się tylko, gdy zmieni się liczba dni (`deadline_days`).
 * Poprawka ceny czy opisu pozycji po tygodniu nie ma odsuwać terminu
 * o tydzień — praca w środku się nie zmieniła.
 *
 * Wołają to serwisy, które zmieniają pozycje i listy (a nie zdarzenia
 * modeli): zapis formatki to kilkanaście zapisów procesów, a termin
 * ma się przeliczyć raz, po całości.
 */
final readonly class OrderDeadline
{
    /**
     * Statusy, w których termin idzie za pozycjami — po kodzie, jak
     * w `OrderPhase`, bo pozycja w słowniku nie niesie znaczenia.
     */
    public const OPEN_STATUSES = ['DO_WYCENY', 'ZLECENIE'];

    public function __construct(
        private OrderSchedule $schedule = new OrderSchedule(),
        private WorkingDays $workingDays = new WorkingDays(),
        private AuditTrail $audit = new AuditTrail(),
    ) {
    }

    public function refresh(int $orderId, ?CarbonInterface $today = null): void
    {
        /** @var Order|null $order */
        $order = Order::query()
            ->with(['status', 'lists.items.processes'])
            ->find($orderId);

        if ($order === null || !$this->follows($order)) {
            return;
        }

        $days = $this->schedule->days($order);

        // Ta sama liczba dni i data juz jest — nic sie nie zmienilo.
        if ($days === $order->deadline_days && ($days === null || $order->client_deadline !== null)) {
            return;
        }

        $before = $order->client_deadline?->toDateString();
        $after = $days === null
            ? null
            : $this->workingDays->add($today ?? Carbon::today(), $days)->toDateString();

        $order->client_deadline = $after === null
            ? null
            : Carbon::createFromFormat('Y-m-d', $after)?->startOfDay();
        $order->deadline_days = $days;
        $order->save();

        if ($before === $after) {
            return;
        }

        $this->audit->write(
            Order::class,
            (int) $order->getKey(),
            [['field' => 'termin klienta', 'before' => $before, 'after' => $after]],
            'deadline_computed',
        );
    }

    /**
     * Czy termin tego zlecenia idzie za pozycjami.
     */
    public function follows(Order $order): bool
    {
        if ($order->deadline_manual) {
            return false;
        }

        return in_array((string) $order->status?->code, self::OPEN_STATUSES, true);
    }
}
