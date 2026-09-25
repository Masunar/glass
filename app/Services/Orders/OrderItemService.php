<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Enum\Section;
use App\Enum\PaneShape;
use Illuminate\Validation\Rule;
use App\Models\Order;
use App\Models\InvoiceType;
use App\Models\Process;
use App\Models\Product;
use App\Models\OrderList;
use App\Models\OrderItem;
use App\Models\OrderPane;
use App\Support\Normalize;
use App\Services\AuditTrail;
use App\Services\Warehouse\OrderStock;
use App\Models\ProductService;
use App\Models\OrderItemProcess;
use App\DTO\Pricing\PaneSpecification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Formatki i usługi na zleceniu.
 *
 * Cena pozycji jest **snapshotem**, nie odwołaniem do cennika: oferta
 * wystawiona wczoraj musi pokazywać tę samą kwotę po dzisiejszej
 * dostawie, która zmieniła cenę zakupu. Razem z kwotą zapisuje się
 * ścieżka wyliczenia — przy czterech nakładających się poziomach ceny
 * bez niej nie da się odpowiedzieć, skąd wzięła się ta liczba.
 *
 * Przeliczenie następuje wyłącznie przy zapisie pozycji. Nie ma tu
 * żadnego „odśwież ceny", bo to właśnie ciche przeliczanie było
 * największą wadą starego cennika.
 */
final readonly class OrderItemService
{
    private const MAX_MM = 6000;

    /** Ile formatek wchodzi jednym zapisem hurtowym. */
    private const MAX_BATCH = 50;

    public function __construct(
        private OrderPricing $pricing = new OrderPricing(),
        private OrderValue $value = new OrderValue(),
        private OrderDiscountService $discounts = new OrderDiscountService(),
        private OrderTabs $tabs = new OrderTabs(),
        private OrderSchedule $schedule = new OrderSchedule(),
        private AuditTrail $audit = new AuditTrail(),
        private OrderFittingService $fittings = new OrderFittingService(),
        private OrderStock $stock = new OrderStock(),
        private OrderDeadline $deadline = new OrderDeadline(),
    ) {
    }

    /**
     * Ekran formatek: listy z pozycjami, sumy i słowniki potrzebne
     * formularzowi.
     *
     * @return array<string, mixed>
     */
    public function board(int $orderId): array
    {
        /** @var Order $order */
        $order = Order::query()
            ->with([
                'contractor',
                'status',
                'invoiceType',
                'discounts',
                'lists.items.pane',
                'lists.items.product.group',
                'lists.items.processes.process',
            ])
            ->findOrFail($orderId);

        $lists = [];
        $squareMeters = 0.0;
        $runningMeters = 0.0;
        $weight = 0.0;

        /** @var OrderList $list */
        foreach ($order->lists->sortBy('number') as $list) {
            $glass = [];
            $fittings = [];
            $services = [];
            $listNet = 0.0;
            $listDays = null;

            /** @var OrderItem $item */
            foreach ($list->items->sortBy('position') as $item) {
                $row = $this->row($item);
                $listNet += (float) $row['total'];

                if ($item->section === Section::GLASS) {
                    $glass[] = $row;

                    if ($row['days'] > 0 && ($listDays === null || $row['days'] > $listDays)) {
                        $listDays = (int) $row['days'];
                    }

                    if ($list->is_included) {
                        $squareMeters += (float) $row['m2'];
                        $runningMeters += (float) $row['mb'];
                        $weight += (float) $row['kg'];
                    }

                    continue;
                }

                if ($item->section === Section::FITTINGS) {
                    $fittings[] = $row;

                    continue;
                }

                $services[] = $row;
            }

            // Stan magazynowy przy okuciu, jednym zapytaniem na liste.
            // W starym systemie te kolumne nazywano „Obecna wartosc"
            // i raz pokazywala kwote pozycji, raz stan — tutaj nazywa
            // sie tym, czym jest.
            $fittings = $this->withStock($fittings);

            $lists[] = [
                'id' => (int) $list->getKey(),
                'number' => (int) $list->number,
                'days' => $listDays,
                'name' => $list->name,
                'role' => $list->role->value,
                'is_included' => (bool) $list->is_included,
                'is_on_hold' => (bool) $list->is_on_hold,
                // `null` znaczy „jak w typie faktury" — ekran musi
                // umiec pokazac te roznice, bo 0 % to inna decyzja.
                'vat_rate' => $list->vat_rate,
                'comment' => $list->comment,
                'net' => $this->money($listNet),
                'glass' => $glass,
                'fittings' => $fittings,
                'services' => $services,
            ];
        }

        return [
            'order' => [
                'id' => (int) $order->getKey(),
                'number' => (int) $order->number,
                'status' => $order->status?->name,
                'contractor' => $order->contractor?->displayName(),
            ],
            'tabs' => $this->tabs->counts($order),
            'lists' => $lists,
            // Sumy pieniezne licza sie w jednym miejscu dla wszystkich
            // ekranow; tutaj dochodza tylko wielkosci fizyczne szkla.
            'totals' => $this->value->totals($order)->toArray() + [
                'm2' => round($squareMeters, 2),
                'mb' => round($runningMeters, 2),
                'kg' => round($weight, 2),
                // Formatki ida przez hale rownolegle, wiec zlecenie trwa
                // tyle, ile najdluzsza z nich.
                'days' => $this->schedule->days($order),
            ],
            'discounts' => $this->discounts->board($order),
            // Stawki do wyboru na liscie i to, czego stawka `null`
            // znaczy w tym zleceniu. Bez domyslnej ekran musialby
            // napisac „jak w typie faktury" bez podania jakiej.
            'vat' => [
                'default_rate' => $order->invoiceType?->vat_rate,
                'rates' => $this->vatRates(),
                'investment' => $this->value->investmentVat($order)?->toArray(),
            ],
            'catalogue' => [
                'products' => $this->glassCatalogue(),
                'processes' => $this->processCatalogue(),
                'services' => $this->serviceCatalogue(),
                'fittings' => $this->fittings->catalogue(),
                'sets' => $this->fittings->sets(),
            ],
        ];
    }


    /**
     * Stawki VAT do wyboru na liście — te, które są w słowniku typów
     * faktur. Druga lista w kodzie rozjechałaby się z księgowością.
     *
     * @return list<int>
     */
    private function vatRates(): array
    {
        /** @var list<int> */
        return InvoiceType::query()
            ->distinct()
            ->orderBy('vat_rate')
            ->pluck('vat_rate')
            ->map(static fn(mixed $rate): int => (int) $rate)
            ->all();
    }

    /**
     * Wycena formatki **bez zapisu** — podgląd na żywo w panelu.
     *
     * Ta sama droga, którą idzie zapis: `OrderPricing::pane`. Gdyby
     * podgląd liczył po swojemu, pokazywałby kwotę, której zapis nie
     * potwierdzi — a wtedy przestałby być podglądem i stałby się drugą
     * wyceną obok właściwej.
     *
     * Nic nie zapisuje i niczego nie waliduje poza tym, co potrzebne do
     * policzenia: człowiek w trakcie wpisywania ma niekompletny
     * formularz i nie chce dostawać za to czerwonych pól.
     *
     * Przy wycenie hurtem (`sizes`) szczegóły — kroki materiału
     * i etapy — pokazuje pierwszy kompletny wiersz, a `batch` dokłada
     * kwotę każdego wiersza i sumę paczki. Każdy wiersz liczy ta sama
     * droga co pojedyncza formatka, więc suma paczki to dokładnie to,
     * co zapisze `savePanes`.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function preview(int $orderId, array $input): array
    {
        $sizes = $this->sizes($input['sizes'] ?? null);

        if ($sizes === null) {
            return $this->previewOne($orderId, $input);
        }

        $rows = [];
        $total = 0.0;
        $count = 0;
        $pieces = 0;
        $first = null;

        foreach ($sizes as $size) {
            if ((int) $size['width_mm'] <= 0 || (int) $size['height_mm'] <= 0) {
                $rows[] = null;

                continue;
            }

            $one = $this->previewOne($orderId, [...$input, ...$size]);
            $first ??= $one;

            // Bez materialu nie ma kwoty — wiersz nie wchodzi do sumy,
            // zeby stopka nie pokazala „razem 0,00" za cala paczke.
            if ($one['total'] === null) {
                $rows[] = null;

                continue;
            }

            $rows[] = $one['total'];
            $total += (float) $one['total'];
            $count++;
            $pieces += max(1, (int) $size['quantity']);
        }

        $result = $first ?? $this->previewOne($orderId, [...$input, 'width_mm' => 0, 'height_mm' => 0]);
        $result['batch'] = [
            'rows' => $rows,
            'count' => $count,
            'pieces' => $pieces,
            'total' => $count === 0 ? null : $this->money($total),
        ];

        return $result;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function previewOne(int $orderId, array $input): array
    {
        /** @var Order $order */
        $order = Order::query()->with('contractor')->findOrFail($orderId);

        /** @var Product|null $product */
        $product = Product::query()
            ->with('glass')
            ->where('section', Section::GLASS->value)
            ->where('is_active', true)
            ->find($this->id($input['product_id'] ?? null));

        $width = (int) ($input['width_mm'] ?? 0);
        $height = (int) ($input['height_mm'] ?? 0);

        if ($product === null || $width <= 0 || $height <= 0) {
            return ['ready' => false, 'glass_net' => null, 'total' => null, 'processes' => [], 'steps' => []];
        }

        $minBillable = isset($input['min_billable_m2']) && is_numeric($input['min_billable_m2'])
            ? (float) $input['min_billable_m2']
            : null;

        $pane = new PaneSpecification(
            widthMm: $width,
            heightMm: $height,
            quantity: max(1, (int) ($input['quantity'] ?? 1)),
            isIrregularShape: $this->shape($input)->hasShapeSurcharge(),
            isTempered: (bool) ($input['is_tempered'] ?? false),
            isUrgent: (bool) ($input['is_urgent'] ?? false),
            minBillableM2: $minBillable,
        );

        $price = $this->pricing->pane(
            $order,
            $product,
            $pane,
            $this->selections($input['processes'] ?? []),
        );

        $processes = [];

        foreach ($price->processes as $process) {
            $processes[] = [
                'process_id' => $process['process_id'],
                'label' => $process['label'],
                'parameter' => $process['parameter'],
                'unit_net_price' => $process['unit_net_price'],
                'unit_label' => $process['unit_label'],
                'units' => $process['units'],
                'amount' => $process['amount'],
                'unavailable' => $process['unavailable'],
            ];
        }

        return [
            'ready' => true,
            'glass_net' => $price->glassNet,
            'net_price_per_square_meter' => $price->netPricePerSquareMeter,
            'total' => $price->total(),
            'm2' => round($pane->squareMeters(), 3),
            'mb' => round($pane->runningMeters(), 2),
            'kg' => round($price->weightKg, 2),
            'processes' => $processes,
            'steps' => $price->steps,
            'unavailable' => $price->unavailableReason,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, list<string>>, id: int|null}
     */
    public function savePane(int $orderId, array $input, ?int $itemId = null): array
    {
        /** @var Order $order */
        $order = Order::query()->with('contractor')->findOrFail($orderId);

        $errors = $this->validatePane($input);

        if ($errors !== []) {
            return ['errors' => $errors, 'id' => null];
        }

        $list = $this->listFor($order, $input['order_list_id'] ?? null, $itemId);

        if ($list === null) {
            return ['errors' => ['order_list_id' => ['Taka lista nie należy do tego zlecenia.']], 'id' => null];
        }

        /** @var Product|null $product */
        $product = Product::query()
            ->with('glass')
            ->where('section', Section::GLASS->value)
            ->where('is_active', true)
            ->find((int) $input['product_id']);

        if ($product === null) {
            return ['errors' => ['product_id' => ['Taki materiał nie jest w cenniku szkła.']], 'id' => null];
        }

        $selections = $this->selections($input['processes'] ?? []);

        $minBillable = isset($input['min_billable_m2']) && is_numeric($input['min_billable_m2'])
            ? (float) $input['min_billable_m2']
            : null;

        $pane = new PaneSpecification(
            widthMm: (int) $input['width_mm'],
            heightMm: (int) $input['height_mm'],
            quantity: (int) ($input['quantity'] ?? 1),
            isIrregularShape: $this->shape($input)->hasShapeSurcharge(),
            isTempered: (bool) ($input['is_tempered'] ?? false),
            isUrgent: (bool) ($input['is_urgent'] ?? false),
            minBillableM2: $minBillable,
        );

        $price = $this->pricing->pane($order, $product, $pane, $selections);

        $item = DB::transaction(function () use (
            $list,
            $product,
            $pane,
            $price,
            $input,
            $itemId,
        ): OrderItem {
            $item = $itemId === null
                ? new OrderItem()
                : OrderItem::query()->findOrFail($itemId);

            $item->fill([
                'order_list_id' => (int) $list->getKey(),
                'product_id' => (int) $product->getKey(),
                'section' => Section::GLASS->value,
                'name' => $product->name,
                'quantity' => $pane->quantity,
                // Dla szkla cena jednostkowa to cena metra kwadratowego —
                // to ona wynika z cennika. Kwota pozycji jest wynikiem
                // wzoru, nie mnozenia ceny przez ilosc.
                // Brak ceny zapisuje sie jako brak. Zero znaczylo
                // „szklo za darmo" i nie dalo sie go odroznic od ceny.
                'unit_net_price' => $price->netPricePerSquareMeter,
                'amount' => $price->glassNet,
                'price_path' => $price->steps,
                'position' => $item->exists
                    ? $item->position
                    : $this->nextPosition($list),
                'is_urgent' => (bool) ($input['is_urgent'] ?? false),
                // Dwa komentarze o dwoch odbiorcach: uwaga handlowa moze
                // trafic na oferte, instrukcja technologiczna idzie na hale.
                'note' => Normalize::text($input['note'] ?? null),
                'production_note' => Normalize::text($input['production_note'] ?? null),
            ]);
            $item->save();

            OrderPane::query()->updateOrCreate(
                ['order_item_id' => (int) $item->getKey()],
                [
                    'width_mm' => $pane->widthMm,
                    'height_mm' => $pane->heightMm,
                    'shape' => $this->shape($input)->value,
                    'is_tempered' => $pane->isTempered,
                    'needs_mark' => (bool) ($input['needs_mark'] ?? false),
                    'min_billable_m2' => $pane->minBillableM2,
                ],
            );

            // Procesy zapisujemy od nowa: zmiana wymiarow zmienia kwote
            // kazdego z nich, wiec aktualizacja wiersz po wierszu i tak
            // dotknelaby wszystkich.
            OrderItemProcess::query()->where('order_item_id', $item->getKey())->delete();

            $position = 0;

            foreach ($price->processes as $process) {
                OrderItemProcess::query()->create([
                    'order_item_id' => (int) $item->getKey(),
                    'process_id' => $process['process_id'],
                    'product_id' => $process['product_id'],
                    // Parametr to nazwa wybranej pozycji cennikowej —
                    // ta sama wartosc, ktora widzi operator na hali.
                    'parameter' => $process['parameter'],
                    'days' => $process['days'],
                    'comment' => $process['comment'],
                    'unit_net_price' => $process['unit_net_price'],
                    'amount' => $process['amount'],
                    'position' => $position += 10,
                ]);
            }

            return $item;
        });

        $this->audit->write(
            Order::class,
            (int) $order->getKey(),
            [[
                'field' => 'pozycja #' . $item->getKey(),
                'before' => $itemId === null ? null : 'wycena',
                'after' => sprintf(
                    '%s, %d × %d mm × %d szt., %s zł',
                    $product->name,
                    $pane->widthMm,
                    $pane->heightMm,
                    $pane->quantity,
                    $price->total(),
                ),
            ]],
            $itemId === null ? 'item_added' : 'item_updated',
        );

        $this->deadline->refresh((int) $order->getKey());

        return ['errors' => [], 'id' => (int) $item->getKey()];
    }

    /**
     * Kilka formatek z jednego materiału naraz (uwaga klienta z 25.09).
     *
     * Wspólne są materiał, lista, etapy, kształt i flagi; każdy wiersz
     * `sizes` niesie tylko szerokość, wysokość i ilość. Każda formatka
     * zapisuje się tą samą drogą co pojedyncza (`savePane`) — wycena,
     * ścieżka ceny, dziennik i termin są więc dokładnie takie same, jak
     * gdyby dodać je po kolei. Całość w jednej transakcji: paczka
     * wchodzi w całości albo wcale.
     *
     * @param array<string, mixed> $input
     * @return array{errors: array<string, list<string>>, ids: list<int>}
     */
    public function savePanes(int $orderId, array $input): array
    {
        $validator = Validator::make($input, [
            'sizes' => ['required', 'array', 'min:1', 'max:' . self::MAX_BATCH],
            'sizes.*.width_mm' => ['required', 'integer', 'min:1', 'max:' . self::MAX_MM],
            'sizes.*.height_mm' => ['required', 'integer', 'min:1', 'max:' . self::MAX_MM],
            'sizes.*.quantity' => ['nullable', 'integer', 'min:1', 'max:9999'],
        ], [
            'sizes.required' => 'Dodaj co najmniej jeden wymiar.',
            'sizes.max' => 'Jednym zapisem wejdzie najwyżej ' . self::MAX_BATCH . ' formatek.',
            'sizes.*.width_mm.required' => 'Podaj szerokość.',
            'sizes.*.height_mm.required' => 'Podaj wysokość.',
            'sizes.*.width_mm.max' => 'Szerokość powyżej ' . self::MAX_MM . ' mm nie przejdzie przez halę.',
            'sizes.*.height_mm.max' => 'Wysokość powyżej ' . self::MAX_MM . ' mm nie przejdzie przez halę.',
        ]);

        if ($validator->fails()) {
            /** @var array<string, list<string>> $messages */
            $messages = $validator->errors()->messages();

            return ['errors' => $messages, 'ids' => []];
        }

        $sizes = $this->sizes($input['sizes']) ?? [];
        $common = $input;
        unset($common['sizes']);

        return DB::transaction(function () use ($orderId, $sizes, $common): array {
            $ids = [];

            foreach ($sizes as $size) {
                $result = $this->savePane($orderId, [...$common, ...$size]);

                // Wymiary sa juz sprawdzone, wiec blad tutaj dotyczy
                // czesci wspolnej (material, lista) i wychodzi na
                // pierwszym wierszu — zanim cokolwiek sie zapisalo.
                if ($result['errors'] !== [] || $result['id'] === null) {
                    return ['errors' => $result['errors'], 'ids' => []];
                }

                $ids[] = $result['id'];
            }

            return ['errors' => [], 'ids' => $ids];
        });
    }

    /**
     * Wiersze wymiarów z formularza hurtowego; `null`, gdy to zwykła
     * pojedyncza formatka.
     *
     * @return list<array{width_mm: int, height_mm: int, quantity: int}>|null
     */
    private function sizes(mixed $sizes): ?array
    {
        if (!is_array($sizes)) {
            return null;
        }

        $rows = [];

        foreach ($sizes as $size) {
            $row = is_array($size) ? $size : [];
            $rows[] = [
                'width_mm' => is_numeric($row['width_mm'] ?? null) ? (int) $row['width_mm'] : 0,
                'height_mm' => is_numeric($row['height_mm'] ?? null) ? (int) $row['height_mm'] : 0,
                'quantity' => is_numeric($row['quantity'] ?? null) ? max(1, (int) $row['quantity']) : 1,
            ];
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, list<string>>, id: int|null}
     */
    public function saveService(int $orderId, array $input, ?int $itemId = null): array
    {
        /** @var Order $order */
        $order = Order::query()->findOrFail($orderId);

        $validator = Validator::make($input, [
            'name' => ['required', 'string', 'max:200'],
            'quantity' => ['required', 'numeric', 'min:0.001', 'max:99999'],
            'unit_net_price' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'product_id' => ['nullable', 'integer'],
            'is_urgent' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:300'],
            'production_note' => ['nullable', 'string', 'max:300'],
        ], [
            'name.required' => 'Podaj nazwę usługi.',
            'quantity.required' => 'Podaj ilość.',
            'unit_net_price.required' => 'Podaj cenę netto.',
        ]);

        if ($validator->fails()) {
            /** @var array<string, list<string>> $messages */
            $messages = $validator->errors()->messages();

            return ['errors' => $messages, 'id' => null];
        }

        $list = $this->listFor($order, $input['order_list_id'] ?? null, $itemId);

        if ($list === null) {
            return ['errors' => ['order_list_id' => ['Taka lista nie należy do tego zlecenia.']], 'id' => null];
        }

        $quantity = (float) $input['quantity'];
        $unit = (float) $input['unit_net_price'];

        $item = $itemId === null ? new OrderItem() : OrderItem::query()->findOrFail($itemId);

        $item->fill([
            'order_list_id' => (int) $list->getKey(),
            'product_id' => $this->id($input['product_id'] ?? null),
            'section' => Section::SERVICES->value,
            'name' => Normalize::text($input['name']) ?? '',
            'quantity' => $this->money($quantity),
            'unit_net_price' => $this->money($unit),
            'amount' => $this->money($quantity * $unit),
            'position' => $item->exists ? $item->position : $this->nextPosition($list),
            'is_urgent' => (bool) ($input['is_urgent'] ?? false),
            'note' => Normalize::text($input['note'] ?? null),
            'production_note' => Normalize::text($input['production_note'] ?? null),
        ]);
        $item->save();

        $this->audit->write(
            Order::class,
            (int) $order->getKey(),
            [[
                'field' => 'usługa #' . $item->getKey(),
                'before' => $itemId === null ? null : 'wycena',
                'after' => sprintf('%s, %s zł', $item->name, $this->money($quantity * $unit)),
            ]],
            $itemId === null ? 'item_added' : 'item_updated',
        );

        $this->deadline->refresh((int) $order->getKey());

        return ['errors' => [], 'id' => (int) $item->getKey()];
    }

    /**
     * @return array{errors: array<string, list<string>>}
     */
    public function delete(int $orderId, int $itemId): array
    {
        /** @var Order $order */
        $order = Order::query()->findOrFail($orderId);

        /** @var OrderItem|null $item */
        $item = OrderItem::query()
            ->whereHas('list', static fn($query) => $query->where('order_id', $order->getKey()))
            ->find($itemId);

        if ($item === null) {
            return ['errors' => ['item' => ['Ta pozycja nie należy do tego zlecenia.']]];
        }

        $label = $item->name;
        $item->delete();

        $this->audit->write(
            Order::class,
            (int) $order->getKey(),
            [['field' => 'pozycja #' . $itemId, 'before' => $label, 'after' => null]],
            'item_removed',
        );

        $this->deadline->refresh((int) $order->getKey());

        return ['errors' => []];
    }

    /**
     * @param array<string, mixed> $input
     */
    private function shape(array $input): PaneShape
    {
        // Brak kształtu znaczy prostokąt — tak jak dotąd brak flagi.
        return PaneShape::tryFrom((string) ($input['shape'] ?? '')) ?? PaneShape::RECTANGLE;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, list<string>>
     */
    private function validatePane(array $input): array
    {
        $validator = Validator::make($input, [
            'product_id' => ['required', 'integer'],
            'width_mm' => ['required', 'integer', 'min:1', 'max:' . self::MAX_MM],
            'height_mm' => ['required', 'integer', 'min:1', 'max:' . self::MAX_MM],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:9999'],
            // Etap przychodzi jako obiekt z wyborem pozycji, dniami
            // i cena albo — w starszej postaci — jako samo id procesu.
            // Ksztalt rozstrzyga `selections()`; tutaj pilnujemy tylko
            // tego, co da sie sprawdzic bez wiedzy o marszrucie.
            'is_urgent' => ['nullable', 'boolean'],
            'shape' => ['nullable', Rule::enum(PaneShape::class)],
            'note' => ['nullable', 'string', 'max:300'],
            'production_note' => ['nullable', 'string', 'max:300'],
            // Puste znaczy „z parametrow wyceny". Zero jest prawidlowe:
            // rozlicz doslownie tyle, ile jest.
            'min_billable_m2' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'processes' => ['nullable', 'array'],
            'processes.*.process_id' => ['required_with:processes.*.product_id', 'integer'],
            'processes.*.product_id' => ['nullable', 'integer'],
            'processes.*.days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'processes.*.unit_net_price' => ['nullable', 'numeric', 'min:0'],
            'processes.*.comment' => ['nullable', 'string', 'max:300'],
        ], [
            'product_id.required' => 'Wskaż materiał.',
            'shape' => 'Nieznany kształt formatki.',
            'width_mm.required' => 'Podaj szerokość w milimetrach.',
            'height_mm.required' => 'Podaj wysokość w milimetrach.',
            'processes.*.days.max' => 'Etap trwający ponad rok to nie etap.',
            'width_mm.max' => 'Szerokość powyżej ' . self::MAX_MM . ' mm nie przejdzie przez halę.',
            'height_mm.max' => 'Wysokość powyżej ' . self::MAX_MM . ' mm nie przejdzie przez halę.',
        ]);

        if ($validator->fails()) {
            /** @var array<string, list<string>> $messages */
            $messages = $validator->errors()->messages();

            return $messages;
        }

        return [];
    }

    /**
     * Lista, do której trafia pozycja. Przy edycji zostaje ta, w której
     * pozycja już jest — przenoszenie między listami to osobna decyzja.
     */
    private function listFor(Order $order, mixed $listId, ?int $itemId): ?OrderList
    {
        $id = $this->id($listId);

        // Przy edycji wskazana lista wygrywa z dotychczasowa: zmiana
        // listy w panelu pozycji ma ja przeniesc, a nie zostac cicho
        // zignorowana. Bez wskazania zostaje tam, gdzie byla.
        if ($itemId !== null && $id === null) {
            /** @var OrderItem|null $item */
            $item = OrderItem::query()->find($itemId);
            $list = $item?->list;

            return $list instanceof OrderList && $list->order_id === $order->getKey() ? $list : null;
        }

        /** @var OrderList|null */
        return OrderList::query()
            ->where('order_id', $order->getKey())
            ->when($id !== null, static fn($query) => $query->whereKey($id))
            ->orderBy('number')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function withStock(array $rows): array
    {
        $ids = [];

        foreach ($rows as $row) {
            if (is_int($row['product_id'])) {
                $ids[] = $row['product_id'];
            }
        }

        $levels = $this->stock->levelsFor($ids);

        foreach ($rows as $index => $row) {
            $productId = $row['product_id'];
            $rows[$index]['in_stock'] = is_int($productId)
                ? ($levels[$productId] ?? 0.0)
                : null;
        }

        return $rows;
    }

    private function row(OrderItem $item): array
    {
        $processes = [];
        $processAmount = 0.0;
        $days = 0;

        foreach ($item->processes->sortBy('position') as $entry) {
            $processes[] = [
                'process_id' => (int) $entry->process_id,
                'code' => $entry->process?->code,
                'name' => $entry->process?->name,
                'product_id' => $entry->product_id,
                // Nazwa wybranej pozycji — „Faza 15mm". To ona odroznia
                // dwa fazowania na tej samej szybie.
                'parameter' => $entry->parameter,
                'days' => $entry->days,
                'comment' => $entry->comment,
                'unit_net_price' => $entry->unit_net_price,
                'amount' => $entry->amount,
            ];

            $processAmount += (float) $entry->amount;
            $days += (int) $entry->days;
        }

        $pane = $item->pane;
        $spec = $pane === null
            ? null
            : new PaneSpecification(
                widthMm: $pane->width_mm,
                heightMm: $pane->height_mm,
                quantity: (int) $item->quantity,
                isIrregularShape: $pane->shape->hasShapeSurcharge(),
                isTempered: (bool) $pane->is_tempered,
                isUrgent: (bool) $item->is_urgent,
            );

        $thickness = $item->product?->glass?->thickness_mm;

        return [
            'id' => (int) $item->getKey(),
            'position' => $item->position,
            'section' => $item->section->value,
            'product_id' => $item->product_id,
            'group' => $item->product?->group?->name,
            'name' => $item->name,
            'thickness_mm' => $thickness,
            'quantity' => $item->quantity,
            'unit_net_price' => $item->unit_net_price,
            'amount' => $item->amount,
            'total' => $this->money((float) $item->amount + $processAmount),
            'processes' => $processes,
            // Dni pozycji to suma jej etapow — tak samo liczyl to stary
            // system: ciecie 2 + poler 3 + faza 6 + CNC 12 = 23.
            'days' => $days,
            'price_path' => $item->price_path ?? [],
            'width_mm' => $pane?->width_mm,
            'height_mm' => $pane?->height_mm,
            'shape' => ($pane->shape ?? PaneShape::RECTANGLE)->value,
            'is_tempered' => (bool) ($pane->is_tempered ?? false),
            'needs_mark' => (bool) ($pane->needs_mark ?? false),
            'is_urgent' => (bool) $item->is_urgent,
            'note' => $item->note,
            'production_note' => $item->production_note,
            // `null` znaczy „z parametrow wyceny", nie zero.
            'min_billable_m2' => $pane?->min_billable_m2,
            'm2' => $spec === null ? null : round($spec->squareMeters(), 3),
            'mb' => $spec === null ? null : round($spec->runningMeters(), 2),
            'kg' => $spec === null || $thickness === null
                ? null
                // Do tego miejsca dochodzimy tylko przy znanej grubosci,
                // a ta bierze sie z tego samego produktu i tego samego
                // szkla — wiec oba na pewno istnieja.
                : $item->product->glass->weightOfPane(
                    $spec->widthMm,
                    $spec->heightMm,
                    $spec->quantity,
                ),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function glassCatalogue(): array
    {
        $rows = [];

        /** @var iterable<Product> $products */
        $products = OrderPricing::glassProducts()->with('group')->get();

        foreach ($products as $product) {
            $rows[] = [
                'id' => (int) $product->getKey(),
                'name' => $product->name,
                'group' => $product->group?->name,
                'thickness_mm' => $product->glass?->thickness_mm,
                'is_tempered_by_default' => (bool) ($product->glass->is_tempered_by_default ?? false),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function processCatalogue(): array
    {
        $rows = [];

        /** @var iterable<Process> $processes */
        $processes = Process::query()
            ->where('is_active', true)
            ->orderBy('default_order')
            ->get();

        /** @var iterable<ProductService> $services */
        $services = ProductService::query()
            ->with('product')
            ->whereNotNull('process_id')
            ->get();

        /** @var array<int, list<array<string, mixed>>> $byProcess */
        $byProcess = [];

        foreach ($services as $service) {
            $product = $service->product;

            if (!$product instanceof Product || !$product->is_active) {
                continue;
            }

            $byProcess[(int) $service->process_id][] = [
                'product_id' => (int) $product->getKey(),
                'name' => $product->name,
                // Grubosc szkla zawezajaca liste. Ekran filtruje po niej
                // sam, bo po zmianie materialu lista ma sie przestawic
                // bez ponownego pytania serwera.
                'glass_thickness_mm' => $service->glass_thickness_mm,
                'unit' => $product->unit->value,
            ];
        }

        foreach ($processes as $process) {
            $id = (int) $process->getKey();
            $items = $byProcess[$id] ?? [];

            usort($items, static fn(array $a, array $b): int => [
                $a['glass_thickness_mm'] ?? 0.0, $a['name'],
            ] <=> [
                $b['glass_thickness_mm'] ?? 0.0, $b['name'],
            ]);

            $rows[] = [
                'id' => $id,
                'code' => $process->code,
                'name' => $process->name,
                'is_subcontracted' => (bool) $process->is_subcontracted,
                // Czas trwania ze slownika — punkt wyjscia, nie wyrok.
                'duration_days' => $process->duration_days,
                'items' => $items,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function serviceCatalogue(): array
    {
        $rows = [];

        /** @var iterable<Product> $products */
        $products = Product::query()
            ->where('section', Section::SERVICES->value)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        foreach ($products as $product) {
            $rows[] = ['id' => (int) $product->getKey(), 'name' => $product->name];
        }

        return $rows;
    }

    /**
     * @param mixed $processes
     * @return list<int>
     */
    /**
     * Wybrane procesy razem z tym, co przy nich wpisano.
     *
     * Ten sam proces może wystąpić kilka razy — na jednej szybie bywa
     * faza 15 mm i faza 25 mm, i są to dwie różne prace o różnej cenie.
     * Dlatego nie odsiewamy powtórzeń po `process_id`, tylko po parze
     * proces + wybrana pozycja cennikowa.
     *
     * Przyjmujemy też starą postać — samą listę identyfikatorów — bo
     * tak wołają istniejące testy i tak wygląda zapis bez wyboru.
     *
     * @return list<array<string, mixed>>
     */
    private function selections(mixed $processes): array
    {
        if (!is_array($processes)) {
            return [];
        }

        $rows = [];
        $seen = [];

        foreach ($processes as $value) {
            $row = is_array($value) ? $value : ['process_id' => $value];
            $processId = (int) ($row['process_id'] ?? 0);

            if ($processId <= 0) {
                continue;
            }

            $productId = isset($row['product_id']) && is_numeric($row['product_id'])
                ? (int) $row['product_id']
                : null;

            $key = $processId . ':' . ($productId ?? '');

            if (in_array($key, $seen, true)) {
                continue;
            }

            $seen[] = $key;

            $rows[] = [
                'process_id' => $processId,
                'product_id' => $productId,
                'days' => $row['days'] ?? null,
                'comment' => $row['comment'] ?? null,
                'unit_net_price' => $row['unit_net_price'] ?? null,
            ];
        }

        return $rows;
    }

    private function nextPosition(OrderList $list): int
    {
        return ((int) OrderItem::query()
            ->where('order_list_id', $list->getKey())
            ->max('position')) + 10;
    }

    private function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    private function id(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === 0 || $value === '0') {
            return null;
        }

        return (int) $value;
    }
}
