<?php

declare(strict_types=1);

namespace App\Alerts\Conditions;

use Carbon\Carbon;
use App\Models\Product;
use App\Models\StockLevel;
use App\Enum\AlertCategory;
use App\Alerts\AlertCondition;
use App\Enum\AlertConditionType;

/**
 * Stan magazynowy poniżej progu minimalnego.
 *
 * **Ta sama definicja, co sugestia zakupu** (`StockLevel::toOrder()`):
 * liczona od stanu **fizycznego**, nie dostępnego — rezerwacja mówi,
 * komu towar obiecano, a nie że go nie ma na półce. Własna definicja
 * braku rozjechałaby się z ekranem magazynu przy pierwszej zmianie,
 * a byłby to rozjazd bez objawów: obie strony wyglądałyby poprawnie.
 *
 * **Brak progu nie jest progiem zerowym.** Pozycja z `min_quantity`
 * równym zeru nie ma ustawionego minimum i nie alarmuje nigdy —
 * inaczej cały katalog zapaliłby się pierwszego dnia.
 */
final class StockBelowMinimum implements AlertCondition
{
    public function type(): AlertConditionType
    {
        return AlertConditionType::STOCK_BELOW_MINIMUM;
    }

    public function label(): string
    {
        return 'Stan poniżej minimum';
    }

    public function category(): AlertCategory
    {
        return AlertCategory::MISSING_DATA;
    }

    public function module(): string
    {
        return 'mag';
    }

    public function alertable(): string
    {
        return Product::class;
    }

    public function resource(): string
    {
        return 'warehouse';
    }

    public function parameters(): array
    {
        return [];
    }

    public function find(Carbon $day, array $params): array
    {
        /** @var iterable<StockLevel> $levels */
        $levels = StockLevel::query()
            ->where('min_quantity', '>', 0)
            ->whereColumn('quantity', '<', 'min_quantity')
            ->get();

        $found = [];

        foreach ($levels as $level) {
            $missing = (float) $level->min_quantity - (float) $level->quantity;

            // Brakujaca ilosc, nie stan: rosnie, gdy robi sie gorzej,
            // wiec odhaczenie samo wraca przy poglebieniu braku.
            $found[(int) $level->product_id] = $this->amount($missing);
        }

        return $found;
    }

    public function subjects(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        /** @var array<int, string> $names */
        $names = Product::query()->whereIn('id', $ids)->pluck('name', 'id')->all();

        $rows = [];

        foreach ($names as $id => $name) {
            $rows[(int) $id] = [
                'label' => (string) $name,
                // Magazyn nie ma ekranu pojedynczego produktu — lista
                // braków jest najblizszym miejscem, do ktorego da sie
                // pojsc. Sciezka do nieistniejacego ekranu byłaby
                // gorsza niz jej brak.
                'path' => '/magazyn',
            ];
        }

        return $rows;
    }

    /** Ilość bez zer na końcu: „3" zamiast „3.000". */
    private function amount(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    }
}
