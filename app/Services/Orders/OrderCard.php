<?php

declare(strict_types=1);

namespace App\Services\Orders;

use Carbon\Carbon;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\InvoiceType;
use App\Models\Process;
use App\Enum\AddressKind;
use App\Models\OrderItem;
use App\Models\OrderList;
use App\Models\AuditEntry;
use App\Models\ContractorAddress;
use App\Services\Offers\OfferBoard;
use App\Services\Production\ProductionQueue;
use App\Services\Tempering\TemperingBoard;

/**
 * Karta zlecenia — jedno miejsce decyzji.
 *
 * Ekran odpowiada na cztery pytania w tej kolejności: ile to jest warte,
 * na kiedy, czy klient ma jeszcze limit i co można z tym zrobić teraz.
 * Reszta — pozycje, formatki, komentarze — jest uzasadnieniem tych
 * czterech odpowiedzi, nie osobnym tematem.
 *
 * **Czego tu nie ma i dlaczego.** Ścieżka produkcji pokazuje stan
 * etapów dopiero od chwili wejścia zlecenia na produkcję — wcześniej
 * zadań nie ma, bo marszruta to jeszcze plan, nie praca. Pasek „Do zapłaty"
 * milczy o procencie, dopóki zlecenie nie ma typu faktury: bez stawki
 * VAT nie znamy kwoty brutto, a procent liczony od netto pokazywałby
 * spłacone więcej, niż jest.
 */
final readonly class OrderCard
{
    public function __construct(
        private OrderNextStep $nextStep = new OrderNextStep(),
        private OrderValue $value = new OrderValue(),
        private OrderTabs $tabs = new OrderTabs(),
        private ContractorBalance $balance = new ContractorBalance(),
        private ProductionQueue $production = new ProductionQueue(),
        private OrderSchedule $schedule = new OrderSchedule(),
        private OrderDeadline $deadlineRule = new OrderDeadline(),
        private TemperingBoard $tempering = new TemperingBoard(),
        private OfferBoard $offers = new OfferBoard(),
        private OrderOwnerService $owners = new OrderOwnerService(),
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function card(int $orderId, ?Carbon $today = null): array
    {
        $day = ($today ?? Carbon::today())->startOfDay();

        /** @var Order $order */
        $order = Order::query()
            ->with([
                'contractor.addresses',
                'contractor.primaryContact',
                'status',
                'pickupLocation',
                'creator',
                'owner',
                'invoiceType',
                'creditOverrider',
                'fittingsPreparer',
                'discounts',
                'lists.items.pane',
                'lists.items.processes.process',
                'payments',
            ])
            ->findOrFail($orderId);

        $steps = $this->nextStep->forOrder($order);
        $totals = $this->value->totals($order);

        return [
            'order' => $this->header($order, $day),
            'tabs' => $this->tabs->counts($order),
            'money' => $totals->toArray(),
            // Kandydaci na prowadzacego jada z karta, bo zmiana
            // prowadzacego jest jedna decyzja, a nie ekranem: druga
            // podroz po liste ludzi otwieralaby pusta szuflade.
            'owners' => $this->owners->candidates(),
            'payment' => $this->payment($order, $totals->gross),
            'credit' => $this->credit($order, (float) $totals->net, $totals->gross),
            // Typy faktury do szuflady „dane do faktury" — jada z karta,
            // bo wybor typu to jedna decyzja, a nie osobny ekran.
            'invoice_types' => $this->invoiceTypes(),
            // `null` znaczy, ze zlecenie nie bylo jeszcze ofertowane —
            // inaczej niz „zero ofert", ktore trzeba przeczytac, zeby
            // dowiedziec sie tego samego.
            'offers' => $this->offers->summary($order),
            'steps' => array_map(static fn($step): array => $step->toArray(), $steps),
            'path' => $this->path($order),
            // Brakujace przejscie w druga strone: hartownia wiedziala
            // o zleceniu, zlecenie o hartowni nie. `null` znaczy, ze
            // nie ma nic do hartowania.
            'tempering' => $this->tempering->forOrder($order),
            'lists' => $this->lists($order),
            'history' => $this->history($order),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function header(Order $order, Carbon $day): array
    {
        $contractor = $order->contractor;
        // Adresy są już wczytane, więc szukamy w kolekcji zamiast
        // dokładać zapytanie na każdą kartę.
        $address = $contractor?->addresses->firstWhere('kind', AddressKind::REGISTERED);
        $deadline = $order->effectiveDeadline();

        return [
            'id' => (int) $order->getKey(),
            'number' => (int) $order->number,
            'created_at' => $order->getRawOriginal('created_at'),
            'created_by' => $this->personName($order),
            // Zakladajacy i prowadzacy to dwie rozne odpowiedzi: „kto to
            // wpisal" i „kogo pytac dzisiaj". Karta pokazuje obie.
            'owner' => $order->owner === null ? null : OrderOwnerService::name($order->owner),
            'owner_id' => $order->owner_id === null ? null : (int) $order->owner_id,
            'status' => $order->status?->name,
            'status_code' => $order->status?->code,
            'is_final' => (bool) ($order->status->is_final ?? false),
            'is_on_hold' => (bool) $order->is_on_hold,
            'hold_reason' => $order->hold_reason,
            'has_open_claim' => (bool) $order->has_open_claim,
            'contractor' => $contractor === null ? null : [
                'id' => (int) $contractor->getKey(),
                'name' => $contractor->name,
                'display_name' => $contractor->displayName(),
                'tax_id' => $contractor->tax_id,
                'phone' => $contractor->phone,
                'email' => $contractor->email,
                'address' => $address === null ? null : $this->addressLine($address),
                'city' => $address === null
                    ? null
                    : trim(($address->postal_code ?? '') . ' ' . ($address->city ?? '')),
                'contact' => $contractor->primaryContact?->fullName(),
            ],
            'delivery' => [
                'method' => $order->delivery_method->value,
                'place' => $order->pickupLocation?->name,
                'address' => $order->delivery_address,
                'contact' => $order->delivery_contact,
            ],
            'invoice' => [
                'type_id' => $order->invoice_type_id === null ? null : (int) $order->invoice_type_id,
                'type' => $order->invoiceType?->name,
                'vat_rate' => $order->invoiceType?->vat_rate,
                'buyer_name' => $order->buyer_name,
                'buyer_tax_id' => $order->buyer_tax_id,
                'buyer_address' => $order->buyer_address,
                'accounting_note' => $order->accounting_note,
            ],
            // Inwestycja jest w naglowku, a nie przy kwotach, bo to
            // dana o obiekcie: metraz domu nie zmienia sie od tego, co
            // jest na liscie. Kwoty tylko z niej korzystaja.
            'investment' => $this->value->investmentVat($order)?->toArray(),
            // Szacowana liczba dni bierze sie wylacznie z dni wpisanych
            // przy etapach. Z niej `OrderDeadline` wylicza termin klienta.
            'estimated_days' => $this->schedule->days($order),
            'deadline' => [
                'client' => $order->client_deadline?->toDateString(),
                'production' => $order->production_deadline?->toDateString(),
                'shifted' => $order->shifted_deadline?->toDateString(),
                'shift_reason' => $order->shift_reason,
                // Skad termin klienta: wpisany recznie, liczony z pozycji
                // albo liczony, ale zamkniety po przekazaniu na produkcje.
                'source' => $order->deadline_manual
                    ? 'manual'
                    : ($this->deadlineRule->follows($order) ? 'auto' : 'frozen'),
                'computed_days' => $order->deadline_days,
                // Magazyn odhaczyl okucia jako przygotowane (lista
                // kompletacji) — kto i kiedy.
                'fittings_prepared' => $order->fittings_prepared_at === null ? null : [
                    'at' => $order->fittings_prepared_at->format('Y-m-d H:i'),
                    'by' => $order->fittingsPreparer === null
                        ? null
                        : OrderOwnerService::name($order->fittingsPreparer),
                ],
                'effective' => $deadline?->toDateString(),
                'days_left' => $deadline === null ? null : (int) $day->diffInDays($deadline, false),
            ],
            // Cztery komentarze to nie powtórzenie: treść dla montażysty
            // nie może trafić na ofertę do klienta.
            'comments' => [
                'short' => $order->short_note,
                'production' => $order->production_comment,
                'installer' => $order->installer_comment,
                'offer' => $order->offer_comment,
            ],
        ];
    }

    /**
     * Wpłaty i saldo zlecenia.
     *
     * Procent liczymy wyłącznie od brutto (`OrderProgress`). Zlecenie bez typu faktury nie
     * ma znanej kwoty do zapłaty, więc pasek pokazuje samą sumę wpłat
     * i mówi, czego brakuje — zamiast dzielić przez netto i twierdzić,
     * że klient zapłacił więcej, niż zapłacił.
     *
     * @return array<string, mixed>
     */
    private function payment(Order $order, ?string $gross): array
    {
        $paid = 0.0;

        // Korekta jest zwyklym wierszem z kwota ujemna, wiec sumuje sie
        // sama — nie ma tu zadnego wyjatku do obsluzenia.
        foreach ($order->payments as $payment) {
            $paid += (float) $payment->amount_base;
        }

        $due = $gross === null ? null : (float) $gross - $paid;

        return [
            'paid' => $this->amount($paid),
            'due' => $due === null ? null : $this->amount($due),
            // Ta sama regula co na liscie zlecen — jedna definicja
            // procentu, nie dwie zaokraglane kazda po swojemu.
            'percent' => OrderProgress::paidPercent($paid, $gross === null ? null : (float) $gross),
            'count' => $order->payments->count(),
            'currency' => PaymentService::BASE_CURRENCY,
        ];
    }

    /**
     * Limit kupiecki zestawiony z całym długiem kontrahenta, nie z tym
     * jednym zleceniem: pytanie brzmi „czy ten klient ma jeszcze limit",
     * a nie „czy mieści się to zlecenie".
     *
     * @return array<string, mixed>|null
     */
    private function credit(Order $order, float $net, ?string $gross): ?array
    {
        $contractor = $order->contractor;

        if ($contractor === null) {
            return null;
        }

        $limit = (float) $contractor->credit_limit;
        // Brutto bierzemy gotowe, a nie przeliczamy netto przez stawke:
        // przy dwoch stawkach na zleceniu jednej stawki po prostu nie ma,
        // a przelicznik dalby kwote, ktorej nie ma na zadnej fakturze.
        $value = $gross === null ? $net : (float) $gross;
        $outstanding = (float) $this->balance->outstanding($contractor);
        $user = Auth::user();

        return [
            'limit' => $this->amount($limit),
            'payment_days' => $contractor->payment_days,
            'order_value' => $this->amount($value),
            'outstanding' => $this->amount($outstanding),
            'exceeds_by' => $outstanding > $limit ? $this->amount($outstanding - $limit) : null,
            'is_gross' => $gross !== null,
            // Zgoda administratora na produkcje mimo limitu — na karcie
            // wprost, kto i dlaczego, a nie ukryta w dzienniku.
            'override' => $order->credit_override_at === null ? null : [
                'by' => $order->creditOverrider === null
                    ? null
                    : OrderOwnerService::name($order->creditOverrider),
                'at' => $order->credit_override_at->format('d.m.Y H:i'),
                'reason' => $order->credit_override_reason,
            ],
            'can_override' => OrderCreditOverride::allowed($user instanceof User ? $user : null),
        ];
    }

    /**
     * @return list<array{id: int, name: string, vat_rate: int}>
     */
    private function invoiceTypes(): array
    {
        /** @var iterable<InvoiceType> $types */
        $types = InvoiceType::query()
            ->where('is_active', true)
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        $rows = [];

        foreach ($types as $type) {
            $rows[] = [
                'id' => (int) $type->getKey(),
                'name' => $type->name,
                'vat_rate' => $type->vat_rate,
            ];
        }

        return $rows;
    }

    /**
     * Ścieżka produkcji zbudowana z procesów faktycznie przypisanych do
     * pozycji, w kolejności ze słownika procesów — razem ze stanem
     * wykonania, o ile zlecenie było już na produkcji. Zanim tam trafi,
     * liczniki są puste: marszruta jest wtedy planem, nie pracą.
     *
     * @return list<array<string, mixed>>
     */
    private function path(Order $order): array
    {
        $progress = $this->production->progressByProcess($order);

        /** @var array<int, array<string, mixed>> $steps */
        $steps = [];

        foreach ($this->includedLists($order) as $list) {
            foreach ($list->items as $item) {
                foreach ($item->processes as $entry) {
                    $process = $entry->process;

                    if (!$process instanceof Process) {
                        continue;
                    }

                    $id = (int) $process->getKey();

                    $steps[$id] ??= [
                        'code' => $process->code,
                        'name' => $process->name,
                        'order' => $process->default_order,
                        'is_subcontracted' => (bool) $process->is_subcontracted,
                        'items' => 0,
                        'amount' => 0.0,
                        'done' => $progress[$id]['done'] ?? null,
                        'tasks' => $progress[$id]['total'] ?? null,
                        'problems' => $progress[$id]['problems'] ?? 0,
                    ];

                    $steps[$id]['items'] = (int) $steps[$id]['items'] + 1;
                    $steps[$id]['amount'] = (float) $steps[$id]['amount'] + (float) $entry->amount;
                }
            }
        }

        usort($steps, static fn(array $a, array $b): int => $a['order'] <=> $b['order']);

        return array_map(
            fn(array $step): array => [
                'code' => $step['code'],
                'name' => $step['name'],
                'is_subcontracted' => $step['is_subcontracted'],
                'items' => $step['items'],
                'amount' => $this->amount((float) $step['amount']),
                // `null` znaczy „zlecenie nie było jeszcze na produkcji",
                // a nie „zero zrobione". To dwie różne rzeczy.
                'done' => $step['done'],
                'tasks' => $step['tasks'],
                'problems' => $step['problems'],
            ],
            $steps,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function lists(Order $order): array
    {
        $rows = [];

        /** @var OrderList $list */
        foreach ($order->lists->sortBy('number') as $list) {
            $items = [];
            $net = 0.0;

            /** @var OrderItem $item */
            foreach ($list->items->sortBy('position') as $item) {
                $pane = $item->pane;
                $processes = [];
                $processAmount = 0.0;

                foreach ($item->processes as $entry) {
                    $processes[] = $entry->process->name ?? '—';
                    $processAmount += (float) $entry->amount;
                }

                $amount = (float) $item->amount + $processAmount;
                $net += $amount;

                $items[] = [
                    'id' => (int) $item->getKey(),
                    'section' => $item->section->value,
                    'name' => $item->name,
                    'quantity' => $item->quantity,
                    'unit_net_price' => $item->unit_net_price,
                    'amount' => $this->amount($amount),
                    'processes' => $processes,
                    'pane' => $pane === null ? null : [
                        'width_mm' => $pane->width_mm,
                        'height_mm' => $pane->height_mm,
                        'shape' => $pane->shape->value,
                        'is_tempered' => (bool) $pane->is_tempered,
                        'needs_mark' => (bool) $pane->needs_mark,
                    ],
                ];
            }

            $rows[] = [
                'id' => (int) $list->getKey(),
                'number' => (int) $list->number,
                'name' => $list->name,
                'role' => $list->role->value,
                'is_included' => (bool) $list->is_included,
                'is_on_hold' => (bool) $list->is_on_hold,
                // `null` znaczy „jak w typie faktury" — ekran musi
                // umiec pokazac te roznice, bo 0 % to inna decyzja.
                'vat_rate' => $list->vat_rate,
                'comment' => $list->comment,
                'net' => $this->amount($net),
                'items' => $items,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function history(Order $order): array
    {
        /** @var iterable<AuditEntry> $entries */
        $entries = AuditEntry::query()
            ->with('user')
            ->where('auditable_type', Order::class)
            ->where('auditable_id', (int) $order->getKey())
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        $rows = [];

        foreach ($entries as $entry) {
            $user = $entry->user;

            $rows[] = [
                'at' => $entry->created_at->format('d.m.Y H:i'),
                'event' => $entry->event,
                'user' => $user === null
                    ? null
                    : trim((string) $user->first_name . ' ' . (string) $user->last_name),
                'changes' => $entry->changes ?? [],
            ];
        }

        return $rows;
    }

    /**
     * @return iterable<OrderList>
     */
    private function includedLists(Order $order): iterable
    {
        foreach ($order->lists as $list) {
            if ($list->is_included) {
                yield $list;
            }
        }
    }

    private function amount(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    private function personName(Order $order): ?string
    {
        $user = $order->creator;

        if ($user === null) {
            return null;
        }

        $name = trim((string) $user->first_name . ' ' . (string) $user->last_name);

        return $name === '' ? null : $name;
    }

    private function addressLine(ContractorAddress $address): string
    {
        $line = trim(($address->street ?? '') . ' ' . ($address->building_number ?? ''));

        if ($address->unit_number !== null && $address->unit_number !== '') {
            $line .= '/' . $address->unit_number;
        }

        return trim($line);
    }
}
