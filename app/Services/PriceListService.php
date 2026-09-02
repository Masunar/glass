<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use App\Enum\Section;
use App\Models\Product;
use App\Models\AuditEntry;
use Illuminate\Support\Str;
use App\Models\PriceSection;
use App\Models\ProductGroup;
use App\Models\PriceListItem;
use Illuminate\Support\Facades\Auth;
use App\Services\Pricing\PriceResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Cennik: macierz produkt × sekcja cenowa.
 *
 * Wartością wiodącą w komórce jest współczynnik narzutu, nie kwota —
 * cennik jest polityką marży, a nie tabelą liczb (`50-cennik.md` §4).
 * Kwota jest pochodną i przeliczana wyłącznie przy świadomym zapisie:
 * w starym systemie dostawa po nowej cenie po cichu przeliczała cały
 * cennik i oferta wystawiona wczoraj przestawała być aktualna.
 */
final readonly class PriceListService
{
    public function __construct(
        private PriceResolver $resolver = new PriceResolver(),
    ) {}

    /**
     * Macierz dla jednej grupy asortymentowej.
     *
     * @return array<string, mixed>
     */
    public function matrix(Section $section, ?int $groupId = null, ?Carbon $date = null): array
    {
        $date ??= Carbon::today();

        $groups = $this->groups($section);
        $group = $groupId === null
            ? $groups->first()
            : $groups->firstWhere('id', $groupId);

        $columns = $this->columns($section);

        return [
            'section' => $section->value,
            'groups' => $groups
                ->map(static fn(ProductGroup $item): array => [
                    'id' => $item->id,
                    'name' => $item->name,
                ])
                ->values()
                ->all(),
            'group_id' => $group?->id,
            'columns' => $columns
                ->map(static fn(PriceSection $item): array => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'is_default' => (bool) $item->is_default,
                ])
                ->values()
                ->all(),
            'rows' => $group === null ? [] : $this->rows($group, $columns, $date),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $cells
     * @return array<string, list<string>> błędy walidacji; puste gdy zapis się powiódł
     */
    public function update(array $cells, ?Carbon $date = null): array
    {
        $today = $date ?? Carbon::today();

        $errors = $this->validate($cells);

        if ($errors !== []) {
            return $errors;
        }

        // Jedna sesja edycji na cały zapis — zmiana dwudziestu komórek
        // jednym kliknięciem ma być w dzienniku jednym wpisem.
        $editSession = (string) Str::uuid();
        $changes = [];

        foreach ($cells as $cell) {
            $productId = (int) $cell['product_id'];
            $priceSectionId = (int) $cell['price_section_id'];
            $coefficient = $this->normalize($cell['coefficient'] ?? null);
            $manual = $this->normalize($cell['manual_net_price'] ?? null);

            $current = $this->itemAt($productId, $priceSectionId, $today);

            $computed = $coefficient === null
                ? null
                : $this->computeFromPurchasePrice($productId, $coefficient, $today);

            if ($this->unchanged($current, $coefficient, $manual, $computed)) {
                continue;
            }

            $changes[] = [
                'field' => sprintf('product:%d/section:%d', $productId, $priceSectionId),
                'before' => $current === null ? null : [
                    'coefficient' => (string) $current->coefficient,
                    'net_price' => $current->effectiveNetPrice(),
                ],
                'after' => $coefficient === null && $manual === null ? null : [
                    'coefficient' => $coefficient,
                    'net_price' => $manual ?? $computed,
                ],
            ];

            $this->writeNewVersion(
                $current,
                $productId,
                $priceSectionId,
                $coefficient,
                $manual,
                $computed,
                $today,
            );
        }

        if ($changes !== []) {
            AuditEntry::query()->create([
                'edit_session_id' => $editSession,
                'auditable_type' => PriceListItem::class,
                'auditable_id' => 0,
                'user_id' => Auth::id(),
                'event' => 'updated',
                'changes' => $changes,
                'ip_address' => request()->ip(),
            ]);
        }

        return [];
    }

    /**
     * @param Collection<int, PriceSection> $columns
     * @return list<array<string, mixed>>
     */
    private function rows(ProductGroup $group, Collection $columns, Carbon $date): array
    {
        /** @var Collection<int, Product> $products */
        $products = Product::query()
            ->with('glass')
            ->where('product_group_id', $group->id)
            ->where('is_active', true)
            ->get()
            ->sortBy([
                static fn(Product $product): float => $product->glass?->thickness_mm ?? 0.0,
                static fn(Product $product): string => $product->name,
            ])
            ->values();

        $rows = [];

        foreach ($products as $product) {
            $purchase = $product->purchasePriceAt($date)?->net_price;
            $cells = [];

            foreach ($columns as $column) {
                $price = $this->resolver->resolve($product, $column, $date);

                $cells[(string) $column->id] = [
                    'coefficient' => $price->coefficient,
                    'net_price' => $price->netPrice,
                    'source' => $price->source?->value,
                    'is_stale' => $price->isStale(),
                    'recomputed_net_price' => $price->recomputedNetPrice,
                    'margin_percent' => $this->marginPercent($purchase, $price->netPrice),
                ];
            }

            $rows[] = [
                'product_id' => $product->id,
                'name' => $product->name,
                'thickness_mm' => $product->glass?->thickness_mm,
                'unit' => $product->unit->value,
                'purchase_net_price' => $purchase,
                'cells' => $cells,
            ];
        }

        return $rows;
    }

    /**
     * Marża na cenie sprzedaży, nie narzut na koszcie.
     *
     * Dziś, żeby wiedzieć, ile się zarabia, trzeba dzielić w pamięci —
     * a to najważniejsza liczba na tym ekranie (`50-cennik.md` §4.1).
     */
    private function marginPercent(?string $purchase, ?string $netPrice): ?float
    {
        if ($purchase === null || $netPrice === null) {
            return null;
        }

        $price = (float) $netPrice;

        if ($price <= 0.0) {
            return null;
        }

        return round((($price - (float) $purchase) / $price) * 100, 1);
    }

    /** @return Collection<int, ProductGroup> */
    private function groups(Section $section): Collection
    {
        /** @var Collection<int, ProductGroup> $groups */
        $groups = ProductGroup::query()
            ->where('section', $section->value)
            ->where('is_active', true)
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        return $groups;
    }

    /** @return Collection<int, PriceSection> */
    private function columns(Section $section): Collection
    {
        /** @var Collection<int, PriceSection> $columns */
        $columns = PriceSection::query()
            ->where('section', $section->value)
            ->where('is_active', true)
            ->orderBy('position')
            ->get();

        return $columns;
    }

    private function computeFromPurchasePrice(int $productId, string $coefficient, Carbon $date): ?string
    {
        /** @var Product|null $product */
        $product = Product::query()->find($productId);
        $purchase = $product?->purchasePriceAt($date)?->net_price;

        return $purchase === null ? null : PriceListItem::computePrice($purchase, $coefficient);
    }

    private function unchanged(
        ?PriceListItem $current,
        ?string $coefficient,
        ?string $manual,
        ?string $computed,
    ): bool {
        if ($current === null) {
            return $coefficient === null && $manual === null;
        }

        return $this->sameNumber((string) $current->coefficient, $coefficient)
            && $this->sameNumber($current->manual_net_price, $manual)
            && $this->sameNumber($current->computed_net_price, $computed);
    }

    private function sameNumber(?string $a, ?string $b): bool
    {
        if ($a === null || $b === null) {
            return $a === $b;
        }

        return abs((float) $a - (float) $b) < 0.00005;
    }

    private function writeNewVersion(
        ?PriceListItem $current,
        int $productId,
        int $priceSectionId,
        ?string $coefficient,
        ?string $manual,
        ?string $computed,
        Carbon $today,
    ): void {
        // Wyczyszczenie obu pól znaczy „produkt nie ma ceny w tej sekcji”.
        // Zamykamy wersję datą, nie kasujemy wiersza — zlecenie sprzed
        // miesiąca musi dać się odtworzyć.
        if ($coefficient === null && $manual === null) {
            $current?->update(['valid_to' => $today->copy()->subDay()]);

            return;
        }

        $attributes = [
            'product_id' => $productId,
            'price_section_id' => $priceSectionId,
            'coefficient' => $coefficient ?? '0',
            'computed_net_price' => $computed,
            'manual_net_price' => $manual,
            'changed_by' => Auth::id(),
        ];

        // Wersja założona dzisiaj jest poprawiana w miejscu — inaczej
        // powstałby wiersz o zerowej długości obowiązywania.
        if ($current !== null && $current->valid_from->isSameDay($today)) {
            $current->update($attributes);

            return;
        }

        $current?->update(['valid_to' => $today->copy()->subDay()]);

        PriceListItem::query()->create($attributes + ['valid_from' => $today]);
    }

    /**
     * @param array<int, array<string, mixed>> $cells
     * @return array<string, list<string>>
     */
    private function validate(array $cells): array
    {
        $errors = [];

        foreach ($cells as $index => $cell) {
            $validator = Validator::make($cell, [
                'product_id' => ['required', 'integer', 'exists:products,id'],
                'price_section_id' => ['required', 'integer', 'exists:price_sections,id'],
                'coefficient' => ['nullable', 'numeric', 'gt:0', 'max:99'],
                'manual_net_price' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            ]);

            if ($validator->fails()) {
                foreach ($validator->errors()->messages() as $field => $messages) {
                    /** @var list<string> $messages */
                    $errors[sprintf('cells.%d.%s', $index, $field)] = $messages;
                }
            }
        }

        return $errors;
    }

    private function normalize(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    private function itemAt(int $productId, int $priceSectionId, Carbon $date): ?PriceListItem
    {
        /** @var PriceListItem|null */
        return PriceListItem::query()
            ->where('product_id', $productId)
            ->where('price_section_id', $priceSectionId)
            ->whereDate('valid_from', '<=', $date)
            ->where(static function (Builder $query) use ($date): void {
                $query->whereNull('valid_to')->orWhereDate('valid_to', '>=', $date);
            })
            ->orderByDesc('valid_from')
            ->first();
    }
}
