<?php

declare(strict_types=1);

namespace App\Services\Orders;

use Carbon\Carbon;
use App\Enum\Section;
use App\Models\Order;
use App\Models\Product;
use App\Models\OrderItem;
use App\Models\OrderList;
use App\Models\FittingSet;
use App\Services\AuditTrail;
use App\Support\Normalize;
use App\Models\FittingSetItem;
use Illuminate\Support\Facades\DB;
use App\Services\Pricing\PriceResolver;
use Illuminate\Support\Facades\Validator;

/**
 * Okucia na zleceniu — trzecia sekcja obok szkła i usług.
 *
 * **Cena okucia idzie z cennika, nie z formularza.** To różni okucia od
 * usług, gdzie kwotę wpisuje handlowiec. Okucie jest pozycją katalogową
 * z ceną zakupu i współczynnikiem, więc przechodzi tą samą czterostopniową
 * drogą co szkło i zostawia ścieżkę wyliczenia. W starym systemie kolumna
 * „Obecna wartość" pokazywała raz kwotę pozycji, raz stan magazynowy,
 * a w modalu edycji zero (`10-zlecenia.md` §4.2) — bo nie było jednego
 * miejsca, które o cenie decyduje.
 *
 * Ręczne nadpisanie ceny istnieje, ale puste pole znaczy „weź z cennika",
 * nie „zero" — tak samo jak przy etapach formatki.
 */
final readonly class OrderFittingService
{
    public function __construct(
        private PriceResolver $prices = new PriceResolver(),
        private AuditTrail $audit = new AuditTrail(),
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, list<string>>, id: int|null}
     */
    public function save(int $orderId, array $input, ?int $itemId = null): array
    {
        /** @var Order $order */
        $order = Order::query()->with('contractor')->findOrFail($orderId);

        $validator = Validator::make($input, [
            'product_id' => ['required', 'integer'],
            'quantity' => ['required', 'numeric', 'min:0.001', 'max:99999'],
            // Puste pole znaczy „z cennika". Zero znaczy zero i jest
            // prawidłową ceną — bywa okucie dorzucane gratis.
            'unit_net_price' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'note' => ['nullable', 'string', 'max:300'],
        ], [
            'product_id.required' => 'Wybierz okucie z katalogu.',
            'quantity.required' => 'Podaj ilość.',
        ]);

        if ($validator->fails()) {
            /** @var array<string, list<string>> $messages */
            $messages = $validator->errors()->messages();

            return ['errors' => $messages, 'id' => null];
        }

        $product = $this->fitting($input['product_id']);

        if ($product === null) {
            return ['errors' => ['product_id' => ['Takiego okucia nie ma w katalogu.']], 'id' => null];
        }

        $list = $this->listFor($order, $input['order_list_id'] ?? null, $itemId);

        if ($list === null) {
            return ['errors' => ['order_list_id' => ['Taka lista nie należy do tego zlecenia.']], 'id' => null];
        }

        $item = $this->write(
            $order,
            $list,
            $product,
            (float) $input['quantity'],
            $input['unit_net_price'] ?? null,
            Normalize::text($input['note'] ?? null),
            $itemId,
        );

        return ['errors' => [], 'id' => (int) $item->getKey()];
    }

    /**
     * Rozwinięcie zestawu na pozycje listy.
     *
     * Zestaw jest **szablonem, nie bytem na zleceniu**: po dołożeniu
     * zostają zwykłe pozycje, które da się pojedynczo skasować i zmienić.
     * Gdyby zestaw został jako całość, handlowiec zmieniający jedną gałkę
     * musiałby rozbijać komplet albo zakładać nowy szablon — i tak
     * właśnie biblioteka starego systemu zapełniła się wpisami `test`,
     * `140` i `Marta Grabowska` (`80-slowniki.md` §3.4).
     *
     * @param array<string, mixed> $input
     * @return array{errors: array<string, list<string>>, ids: list<int>}
     */
    public function addSet(int $orderId, array $input): array
    {
        /** @var Order $order */
        $order = Order::query()->with('contractor')->findOrFail($orderId);

        /** @var FittingSet|null $set */
        $set = FittingSet::query()
            ->with('items')
            ->where('is_active', true)
            ->find($this->id($input['fitting_set_id'] ?? null));

        if ($set === null) {
            return ['errors' => ['fitting_set_id' => ['Takiego zestawu nie ma.']], 'ids' => []];
        }

        $list = $this->listFor($order, $input['order_list_id'] ?? null, null);

        if ($list === null) {
            return ['errors' => ['order_list_id' => ['Taka lista nie należy do tego zlecenia.']], 'ids' => []];
        }

        $ids = [];

        DB::transaction(function () use ($set, $order, $list, &$ids): void {
            /** @var FittingSetItem $entry */
            foreach ($set->items->sortBy('position') as $entry) {
                $product = $this->fitting($entry->product_id);

                if ($product === null) {
                    continue;
                }

                $item = $this->write($order, $list, $product, (float) $entry->quantity, null, null, null);
                $ids[] = (int) $item->getKey();
            }
        });

        return ['errors' => [], 'ids' => $ids];
    }

    /** Katalog okuć do wyboru w panelu. */
    /** @return list<array<string, mixed>> */
    public function catalogue(): array
    {
        $rows = [];

        /** @var iterable<Product> $products */
        $products = Product::query()
            ->with('fitting')
            ->where('section', Section::FITTINGS->value)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        foreach ($products as $product) {
            $rows[] = [
                'id' => (int) $product->getKey(),
                'name' => $product->name,
                'code' => $product->code,
                'finish' => $product->fitting?->finish,
                'dimension' => $product->fitting?->dimension,
            ];
        }

        return $rows;
    }

    /** Biblioteka zestawów — same szablony, bez zapisanych konfiguracji. */
    /** @return list<array<string, mixed>> */
    public function sets(): array
    {
        $rows = [];

        /** @var iterable<FittingSet> $sets */
        $sets = FittingSet::query()
            ->withCount('items')
            ->where('kind', FittingSet::KIND_TEMPLATE)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        foreach ($sets as $set) {
            $rows[] = [
                'id' => (int) $set->getKey(),
                'name' => $set->name,
                'items' => (int) $set->items_count,
            ];
        }

        return $rows;
    }

    private function write(
        Order $order,
        OrderList $list,
        Product $product,
        float $quantity,
        mixed $manualPrice,
        ?string $note,
        ?int $itemId,
    ): OrderItem {
        $resolved = $this->prices->forContractor($product, $order->contractor, Carbon::today());

        $manual = is_numeric($manualPrice) ? $this->money((float) $manualPrice) : null;
        $rate = $manual ?? ($resolved->isAvailable() ? (string) $resolved->netPrice : null);

        $item = $itemId === null ? new OrderItem() : OrderItem::query()->findOrFail($itemId);

        $item->fill([
            'order_list_id' => (int) $list->getKey(),
            'product_id' => (int) $product->getKey(),
            'section' => Section::FITTINGS->value,
            'name' => $product->name,
            'quantity' => $this->money($quantity),
            // Brak ceny zapisuje sie jako brak. Zero znaczyloby „okucie
            // za darmo" i nie dalo sie go odroznic od ceny nieznanej.
            'unit_net_price' => $rate,
            'amount' => $this->money($rate === null ? 0.0 : $quantity * (float) $rate),
            'price_path' => $manual === null
                ? array_map(static fn($step): array => $step->toArray(), $resolved->steps)
                : [],
            'position' => $item->exists ? $item->position : $this->nextPosition($list),
            'note' => $note,
        ]);
        $item->save();

        $this->audit->write(
            Order::class,
            (int) $order->getKey(),
            [[
                'field' => 'okucie #' . $item->getKey(),
                'before' => $itemId === null ? null : 'wycena',
                'after' => sprintf(
                    '%s × %s, %s zł',
                    $product->name,
                    rtrim(rtrim($this->money($quantity), '0'), '.'),
                    $item->amount,
                ),
            ]],
        );

        return $item;
    }

    private function fitting(mixed $productId): ?Product
    {
        /** @var Product|null */
        return Product::query()
            ->with('fitting')
            ->where('section', Section::FITTINGS->value)
            ->where('is_active', true)
            ->find($this->id($productId));
    }

    private function listFor(Order $order, mixed $listId, ?int $itemId): ?OrderList
    {
        $id = $this->id($listId);

        if ($id === null && $itemId !== null) {
            /** @var OrderItem|null $item */
            $item = OrderItem::query()->find($itemId);
            $id = $item === null ? null : (int) $item->order_list_id;
        }

        /** @var OrderList|null */
        return OrderList::query()
            ->where('order_id', $order->getKey())
            ->when($id !== null, static fn($query) => $query->where('id', $id))
            ->orderBy('number')
            ->first();
    }

    private function nextPosition(OrderList $list): int
    {
        return ((int) OrderItem::query()
            ->where('order_list_id', $list->getKey())
            ->max('position')) + 10;
    }

    private function id(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === 0 || $value === '0') {
            return null;
        }

        return (int) $value;
    }

    private function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
