<?php

declare(strict_types=1);

namespace Database\Seeders\Dev;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Order;
use App\Enum\Section;
use App\Models\Status;
use App\Models\Product;
use App\Models\Location;
use App\Enum\ListRole;
use App\Models\OrderList;
use App\Models\OrderPane;
use App\Models\OrderItem;
use App\Enum\StatusDomain;
use App\Models\Contractor;
use App\Enum\DeliveryMethod;
use Salvon\Database\Seeder;
use App\Services\NumberSequence;

/**
 * Zlecenia do pracy nad ekranami.
 *
 * Terminy liczone są **względem dnia odpalenia seedera**, nie wpisane
 * na sztywno — inaczej po tygodniu wszystko wpadłoby do pasma zaległych
 * i lista przestałaby pokazywać to, co miała pokazywać.
 *
 * Rozkład celowo obejmuje przypadki, na których ekran ma być
 * sprawdzany: zlecenie na dziś, dwa zaległe, jedno z otwartą
 * reklamacją, jedno wstrzymane, jedno bez terminu, jedno zamknięte
 * (nie może trafić do zaległych) i jedno z odrzuconą alternatywą
 * (nie może wejść do sumy).
 */
class OrderSeeder extends Seeder
{
    public function run(): void
    {
        if (Order::query()->exists()) {
            return;
        }

        $contractors = Contractor::query()->orderBy('id')->get();
        $products = Product::query()
            ->where('section', Section::GLASS->value)
            ->orderBy('id')
            ->get();

        if ($contractors->isEmpty() || $products->isEmpty()) {
            return;
        }

        $today = Carbon::today();
        $sequence = new NumberSequence();
        $users = User::query()->orderBy('id')->get();
        $stobno = Location::query()->where('name', 'Stobno')->first();

        foreach ($this->rows() as $index => $row) {
            /** @var Status $status */
            $status = Status::findByCode(StatusDomain::ORDER, $row['status']);

            /** @var Order $order */
            $order = Order::query()->create([
                'number' => $sequence->next('order', 24000),
                'contractor_id' => $contractors[$index % $contractors->count()]->id,
                'status_id' => $status->id,
                'location_id' => $stobno?->id,
                'delivery_method' => $row['delivery'],
                'pickup_location_id' => $row['delivery'] === DeliveryMethod::PICKUP->value
                    ? $stobno?->id
                    : null,
                'is_on_hold' => $row['on_hold'] ?? false,
                'hold_reason' => $row['hold_reason'] ?? null,
                'has_open_claim' => $row['claim'] ?? false,
                'short_note' => $row['note'],
                'client_deadline' => $row['deadline_in'] === null
                    ? null
                    : $today->copy()->addDays($row['deadline_in']),
                'created_by' => $users->isEmpty()
                    ? null
                    : $users[$index % $users->count()]->id,
            ]);

            $this->fill($order, $products, $row['panes'], $row['rejected'] ?? false);
        }
    }

    /**
     * Lista zawsze jest jedna wliczona; „odrzucona alternatywa" dostaje
     * drugą, wyłączoną — ekran musi pokazać, że nie wchodzi do sumy.
     *
     * @param \Illuminate\Database\Eloquent\Collection<int, Product> $products
     * @param list<array{w: int, h: int, price: string, irregular?: bool}> $panes
     */
    private function fill(Order $order, $products, array $panes, bool $rejected): void
    {
        $list = $this->list($order, 1, ListRole::COMPONENT->value, true);

        foreach ($panes as $position => $pane) {
            $this->pane($list, $products[$position % $products->count()], $pane, $position);
        }

        if (!$rejected) {
            return;
        }

        $alternative = $this->list($order, 2, ListRole::ALTERNATIVE->value, false);

        $this->pane(
            $alternative,
            $products[0],
            ['w' => 1200, 'h' => 900, 'price' => '640.00'],
            0,
        );
    }

    private function list(Order $order, int $number, string $role, bool $included): OrderList
    {
        /** @var OrderList */
        return OrderList::query()->create([
            'order_id' => $order->id,
            'number' => $number,
            'name' => $included ? null : 'Wariant droższy — odrzucony',
            'role' => $role,
            'is_included' => $included,
        ]);
    }

    /**
     * @param array{w: int, h: int, price: string, irregular?: bool} $spec
     */
    private function pane(OrderList $list, Product $product, array $spec, int $position): void
    {
        /** @var OrderItem $item */
        $item = OrderItem::query()->create([
            'order_list_id' => $list->id,
            'product_id' => $product->id,
            'section' => Section::GLASS->value,
            'name' => $product->name,
            'quantity' => 1,
            'unit_net_price' => $spec['price'],
            'amount' => $spec['price'],
            'position' => $position * 10,
        ]);

        OrderPane::query()->create([
            'order_item_id' => $item->id,
            'width_mm' => $spec['w'],
            'height_mm' => $spec['h'],
            'is_irregular_shape' => $spec['irregular'] ?? false,
            'is_tempered' => true,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(): array
    {
        return [
            [
                'status' => 'ZLECENIE', 'delivery' => DeliveryMethod::INSTALLATION->value,
                'deadline_in' => 0, 'note' => 'montaż potwierdzony telefonicznie',
                'panes' => [
                    ['w' => 1200, 'h' => 900, 'price' => '1240.00'],
                    ['w' => 800, 'h' => 600, 'price' => '520.00'],
                ],
            ],
            [
                'status' => 'ZLECENIE', 'delivery' => DeliveryMethod::PICKUP->value,
                'deadline_in' => 0, 'note' => 'klient odbierze po 16:00',
                'panes' => [['w' => 600, 'h' => 400, 'price' => '380.00']],
            ],
            [
                'status' => 'GOTOWE', 'delivery' => DeliveryMethod::PICKUP->value,
                'deadline_in' => 0, 'note' => 'czeka na odbiór w Stobnie',
                'panes' => [['w' => 1500, 'h' => 1000, 'price' => '1890.00']],
            ],
            [
                // Zalegle z reklamacja — wiersz alarmowy.
                'status' => 'PRODUKCJA', 'delivery' => DeliveryMethod::PICKUP->value,
                'deadline_in' => -2, 'note' => 'reklamacja — nowa szyba w miejsce pękniętej',
                'claim' => true,
                'panes' => [['w' => 2000, 'h' => 1200, 'price' => '2100.00', 'irregular' => true]],
            ],
            [
                'status' => 'PRODUKCJA', 'delivery' => DeliveryMethod::INSTALLATION->value,
                'deadline_in' => -5, 'note' => 'brak okuć przypisanych do formatek',
                'panes' => [['w' => 1800, 'h' => 2100, 'price' => '3020.00']],
            ],
            [
                // Wstrzymane: nalezy do zlecenia, ale nie moze isc dalej.
                'status' => 'ZLECENIE', 'delivery' => DeliveryMethod::DELIVERY->value,
                'deadline_in' => 3, 'note' => 'czeka na potwierdzenie wymiarów przez klienta',
                'on_hold' => true, 'hold_reason' => 'klient nie odesłał rysunku',
                'panes' => [['w' => 900, 'h' => 2000, 'price' => '1460.00']],
            ],
            [
                'status' => 'PRODUKCJA', 'delivery' => DeliveryMethod::PICKUP->value,
                'deadline_in' => 4, 'note' => 'lustro na wzór',
                'panes' => [['w' => 700, 'h' => 500, 'price' => '860.00']],
            ],
            [
                // Wariantowanie oferty: druga lista odrzucona, nie liczy sie.
                'status' => 'DO_WYCENY', 'delivery' => DeliveryMethod::INSTALLATION->value,
                'deadline_in' => 9, 'note' => 'oferta w dwóch wariantach',
                'rejected' => true,
                'panes' => [['w' => 1000, 'h' => 800, 'price' => '980.00']],
            ],
            [
                // Bez terminu — nie moze wpasc do zaleglych.
                'status' => 'DO_WYCENY', 'delivery' => DeliveryMethod::PICKUP->value,
                'deadline_in' => null, 'note' => 'wycena wstępna, termin nieustalony',
                'panes' => [['w' => 1100, 'h' => 700, 'price' => '740.00']],
            ],
            [
                // Zamkniete z data w przeszlosci — status koncowy wygrywa
                // z terminem, wiec nie moze trafic do zaleglych.
                'status' => 'ARCHIWUM', 'delivery' => DeliveryMethod::PICKUP->value,
                'deadline_in' => -30, 'note' => 'rozliczone i zamknięte',
                'panes' => [['w' => 800, 'h' => 800, 'price' => '620.00']],
            ],
        ];
    }
}
