<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\Unit;
use Carbon\Carbon;
use App\Enum\Section;
use App\Models\Product;
use App\Models\AuditEntry;
use Illuminate\Support\Str;
use App\Models\ProductGlass;
use App\Models\ProductGroup;
use App\Models\PurchasePrice;
use App\Models\ProductFitting;
use App\Models\ProductService;
use App\Enum\PurchasePriceSource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;

/**
 * Kartoteka produktów i grup asortymentowych.
 *
 * Trzy zasady wyniesione z analizy starego systemu:
 *
 * 1. Słowniki nie usuwają, tylko dezaktywują. Brakujące identyfikatory
 *    w słowniku typów szkła (S-15) sugerują twarde usunięcia, przez co
 *    historyczne zlecenia mogą wskazywać na nieistniejący produkt.
 *
 * 2. Nazwa jest wymagana i unikalna w grupie. Biblioteka schematów
 *    starego systemu zawiera cztery pozycje „nowa formatka”.
 *
 * 3. Cena zakupu jest wersjonowana i nigdy nadpisywana. Zmiana kosztu
 *    nie może po cichu przeliczyć cen na wystawionych ofertach.
 */
final readonly class ProductCatalogService
{
    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, list<string>>, id: int|null}
     */
    public function saveGroup(array $input, ?int $groupId = null): array
    {
        $rules = [
            'section' => ['required', 'string', 'in:' . $this->sectionValues()],
            'name' => ['required', 'string', 'max:100'],
            'manufacturer' => ['nullable', 'string', 'max:100'],
            'series' => ['nullable', 'string', 'max:100'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ];

        $validator = Validator::make($input, $rules);

        if ($validator->fails()) {
            /** @var array<string, list<string>> $messages */
            $messages = $validator->errors()->messages();

            return ['errors' => $messages, 'id' => null];
        }

        $section = (string) $input['section'];
        $name = trim((string) $input['name']);

        $taken = ProductGroup::query()
            ->where('section', $section)
            ->where('name', $name)
            ->when($groupId !== null, static fn(Builder $query): Builder => $query->whereKeyNot($groupId))
            ->exists();

        if ($taken) {
            return [
                'errors' => ['name' => ['Grupa o tej nazwie już istnieje w tej sekcji.']],
                'id' => null,
            ];
        }

        $group = $groupId === null
            ? new ProductGroup()
            : ProductGroup::query()->findOrFail($groupId);

        $before = $groupId === null ? null : $group->only(['name', 'manufacturer', 'series', 'is_active']);

        $group->fill([
            'section' => $section,
            'name' => $name,
            'manufacturer' => $this->nullable($input['manufacturer'] ?? null),
            'series' => $this->nullable($input['series'] ?? null),
            'comment' => $this->nullable($input['comment'] ?? null),
            'is_active' => (bool) ($input['is_active'] ?? true),
        ]);

        if ($groupId === null) {
            // Nowe grupy lądują na końcu listy. Ręczne numery porządkowe
            // starego systemu kolidowały ze sobą (dwie pozycje 1, dwie 12),
            // przez co kolejność stawała się niedeterministyczna.
            $group->position = (int) ProductGroup::query()
                ->where('section', $section)
                ->max('position') + 10;
        }

        $group->save();

        $this->audit(ProductGroup::class, (int) $group->id, $before, $group->only(
            ['name', 'manufacturer', 'series', 'is_active'],
        ));

        return ['errors' => [], 'id' => (int) $group->id];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, list<string>>, id: int|null}
     */
    public function saveProduct(array $input, ?int $productId = null, ?Carbon $date = null): array
    {
        $today = $date ?? Carbon::today();

        $errors = $this->validateProduct($input, $productId);

        if ($errors !== []) {
            return ['errors' => $errors, 'id' => null];
        }

        /** @var ProductGroup $group */
        $group = ProductGroup::query()->findOrFail((int) $input['product_group_id']);
        $section = $group->section;

        $product = $productId === null
            ? new Product()
            : Product::query()->findOrFail($productId);

        $before = $productId === null ? null : $product->only(['name', 'code', 'unit', 'vat_rate', 'is_active']);

        $product->fill([
            'product_group_id' => $group->id,
            // Sekcja idzie z grupy, nie z formularza — inaczej produkt
            // mógłby wylądować w cenniku innej sekcji niż jego grupa.
            'section' => $section->value,
            'name' => trim((string) $input['name']),
            'code' => $this->nullable($input['code'] ?? null),
            'manufacturer_code' => $this->nullable($input['manufacturer_code'] ?? null),
            'unit' => (string) ($input['unit'] ?? $this->defaultUnit($section)->value),
            'vat_rate' => (int) ($input['vat_rate'] ?? 23),
            'is_made_to_order' => (bool) ($input['is_made_to_order'] ?? false),
            'is_active' => (bool) ($input['is_active'] ?? true),
        ]);

        $product->save();

        $this->saveExtension($product, $section, $input);
        $this->savePurchasePrice($product, $input, $today);

        $this->audit(Product::class, (int) $product->id, $before, $product->only(
            ['name', 'code', 'unit', 'vat_rate', 'is_active'],
        ));

        return ['errors' => [], 'id' => (int) $product->id];
    }

    /**
     * Rozszerzenie 1:1 właściwe dla sekcji. Pola szklane nie mają sensu
     * przy okuciu, więc nie leżą w jednej płaskiej tabeli z nullami.
     *
     * @param array<string, mixed> $input
     */
    private function saveExtension(Product $product, Section $section, array $input): void
    {
        match ($section) {
            Section::GLASS => ProductGlass::query()->updateOrCreate(
                ['product_id' => $product->id],
                [
                    'product_id' => $product->id,
                    'thickness_mm' => (float) $input['thickness_mm'],
                    'variant' => $this->nullable($input['variant'] ?? null),
                    'is_tempered_by_default' => (bool) ($input['is_tempered_by_default'] ?? false),
                ],
            ),
            Section::FITTINGS => ProductFitting::query()->updateOrCreate(
                ['product_id' => $product->id],
                [
                    'product_id' => $product->id,
                    'finish' => $this->nullable($input['finish'] ?? null),
                    'dimension' => $this->nullable($input['dimension'] ?? null),
                ],
            ),
            Section::SERVICES => ProductService::query()->updateOrCreate(
                ['product_id' => $product->id],
                [
                    'product_id' => $product->id,
                    'process_id' => $this->nullable($input['process_id'] ?? null),
                    'glass_thickness_mm' => $this->nullable($input['glass_thickness_mm'] ?? null),
                    'variant' => $this->nullable($input['variant'] ?? null),
                ],
            ),
            default => null,
        };
    }

    /**
     * Zmiana ceny zakupu zamyka poprzednią wersję datą i zakłada nową.
     * Bez tego nie da się odpowiedzieć, dlaczego zlecenie sprzed pół
     * roku miało taką marżę.
     *
     * @param array<string, mixed> $input
     */
    private function savePurchasePrice(Product $product, array $input, Carbon $today): void
    {
        if (!array_key_exists('purchase_net_price', $input)) {
            return;
        }

        $value = $this->nullable($input['purchase_net_price']);
        $current = $product->purchasePriceAt($today);

        if ($value === null) {
            $current?->update(['valid_to' => $today->copy()->subDay()]);

            return;
        }

        $normalized = number_format((float) $value, 2, '.', '');

        if ($current !== null && $current->net_price === $normalized) {
            return;
        }

        if ($current !== null && $current->valid_from->isSameDay($today)) {
            $current->update(['net_price' => $normalized, 'created_by' => Auth::id()]);

            return;
        }

        $current?->update(['valid_to' => $today->copy()->subDay()]);

        PurchasePrice::query()->create([
            'product_id' => $product->id,
            'net_price' => $normalized,
            'source' => PurchasePriceSource::MANUAL->value,
            'valid_from' => $today,
            'created_by' => Auth::id(),
        ]);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, list<string>>
     */
    private function validateProduct(array $input, ?int $productId): array
    {
        $groupId = $input['product_group_id'] ?? null;
        /** @var ProductGroup|null $group */
        $group = $groupId === null ? null : ProductGroup::query()->find((int) $groupId);

        if ($group === null) {
            return ['product_group_id' => ['Wskaż grupę asortymentową.']];
        }

        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:40'],
            'manufacturer_code' => ['nullable', 'string', 'max:40'],
            'unit' => ['nullable', 'string', 'in:' . implode(',', array_column(Unit::cases(), 'value'))],
            'vat_rate' => ['nullable', 'integer', 'in:0,5,8,23'],
            'purchase_net_price' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'is_made_to_order' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];

        if ($group->section === Section::GLASS) {
            // Grubość jest podstawą wagi i sortowania cennika, więc bez
            // niej produkt szklany jest bezużyteczny.
            $rules['thickness_mm'] = ['required', 'numeric', 'gt:0', 'max:200'];
            $rules['variant'] = ['nullable', 'string', 'max:30'];
        }

        if ($group->section === Section::SERVICES) {
            $rules['process_id'] = ['nullable', 'integer', 'exists:processes,id'];
            $rules['glass_thickness_mm'] = ['nullable', 'numeric', 'gt:0', 'max:200'];
        }

        $validator = Validator::make($input, $rules);

        if ($validator->fails()) {
            /** @var array<string, list<string>> $messages */
            $messages = $validator->errors()->messages();

            return $messages;
        }

        $taken = Product::query()
            ->where('product_group_id', $group->id)
            ->where('name', trim((string) $input['name']))
            ->when($productId !== null, static fn(Builder $query): Builder => $query->whereKeyNot($productId))
            ->exists();

        if ($taken) {
            return ['name' => ['Produkt o tej nazwie już istnieje w tej grupie.']];
        }

        return [];
    }

    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed> $after
     */
    private function audit(string $type, int $id, ?array $before, array $after): void
    {
        $changes = [];

        foreach ($after as $field => $value) {
            $previous = $before[$field] ?? null;

            if ($before !== null && $previous === $value) {
                continue;
            }

            $changes[] = ['field' => $field, 'before' => $previous, 'after' => $value];
        }

        if ($changes === []) {
            return;
        }

        AuditEntry::query()->create([
            'edit_session_id' => (string) Str::uuid(),
            'auditable_type' => $type,
            'auditable_id' => $id,
            'user_id' => Auth::id(),
            'event' => $before === null ? 'created' : 'updated',
            'changes' => $changes,
            'ip_address' => request()->ip(),
        ]);
    }

    private function defaultUnit(Section $section): Unit
    {
        return match ($section) {
            Section::GLASS => Unit::SQUARE_METER,
            Section::SERVICES => Unit::RUNNING_METER,
            default => Unit::PIECE,
        };
    }

    private function sectionValues(): string
    {
        return implode(',', array_column(Section::cases(), 'value'));
    }

    private function nullable(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
