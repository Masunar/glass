<?php

declare(strict_types=1);

namespace App\Services\Warehouse;

use RuntimeException;
use App\Models\Product;
use App\Models\Location;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Enum\StockMovementType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

/**
 * Jedyna droga, którą zmienia się stan magazynowy.
 *
 * Każda zmiana to wiersz w rejestrze plus przeliczenie projekcji, w tej
 * samej transakcji. Nie ma metody „ustaw stan" — najbliżej jest
 * `count()`, czyli inwentaryzacja, i ona też zapisuje ruch z różnicą.
 *
 * **Rezerwacja i rozchód zachowują się inaczej i to jest celowe.**
 * Zarezerwować można więcej, niż leży na półce: obietnica złożona
 * klientowi jest faktem niezależnie od stanu, a powstały niedobór to
 * dokładnie sygnał, po który ktoś wchodzi na ekran „Okucia
 * w zamówieniach" (`40-magazyn.md` §4). Wydać więcej, niż leży, już nie
 * można — ujemny stan fizyczny nie opisuje niczego, co da się wziąć
 * z regału. Gdyby stan naprawdę był inny, prostuje to inwentaryzacja.
 */
final readonly class StockLedger
{
    /**
     * Przyjęcie towaru od dostawcy. `$orderId` przy dostawie dodatkowej
     * — towar przyszedł dla konkretnego zlecenia.
     */
    public function receive(
        Product $product,
        float $quantity,
        ?Location $location = null,
        ?string $document = null,
        ?string $note = null,
        ?int $orderId = null,
    ): StockLevel {
        return $this->record(StockMovementType::RECEIPT, $product, $quantity, $location, $orderId, $document, $note);
    }

    /** Wydanie na zlecenie. */
    public function issue(
        Product $product,
        float $quantity,
        ?Location $location = null,
        ?int $orderId = null,
        ?string $note = null,
    ): StockLevel {
        $level = $this->level($product, $location);

        if ((float) $level->quantity < $quantity) {
            throw new RuntimeException(sprintf(
                'Nie można wydać %s %s — na stanie jest %s.',
                rtrim(rtrim(number_format($quantity, 3, '.', ''), '0'), '.'),
                $product->name,
                rtrim(rtrim((string) $level->quantity, '0'), '.'),
            ));
        }

        return $this->record(StockMovementType::ISSUE, $product, $quantity, $location, $orderId, 'RW', $note);
    }

    /**
     * Rezerwacja pod zlecenie. Stan fizyczny bez zmian.
     *
     * Świadomie bez sprawdzenia dostępności — patrz opis klasy.
     */
    public function reserve(
        Product $product,
        float $quantity,
        ?Location $location = null,
        ?int $orderId = null,
    ): StockLevel {
        return $this->record(StockMovementType::RESERVATION, $product, $quantity, $location, $orderId);
    }

    /** Zwolnienie rezerwacji — anulowane zlecenie albo zmiana pozycji. */
    public function release(
        Product $product,
        float $quantity,
        ?Location $location = null,
        ?int $orderId = null,
    ): StockLevel {
        return $this->record(StockMovementType::RELEASE, $product, $quantity, $location, $orderId);
    }

    /**
     * Inwentaryzacja: spisano `$counted` sztuk.
     *
     * Zapisuje **różnicę**, nie wynik. Dzięki temu w rejestrze zostaje
     * ślad „było 5, jest 3", a nie sama trójka bez pochodzenia. Spis
     * zgodny ze stanem nie zostawia wiersza — nie ma czego dokumentować.
     */
    public function count(
        Product $product,
        float $counted,
        ?Location $location = null,
        ?string $note = null,
    ): StockLevel {
        $level = $this->level($product, $location);
        $difference = round($counted - (float) $level->quantity, 3);

        if ($difference === 0.0) {
            return $level;
        }

        return $this->record(
            StockMovementType::CORRECTION,
            $product,
            $difference,
            $location,
            null,
            null,
            $note,
        );
    }

    /** Progi zamówienia to decyzja człowieka, więc ustawia się je wprost. */
    public function thresholds(
        Product $product,
        float $min,
        float $max,
        ?Location $location = null,
    ): StockLevel {
        $level = $this->level($product, $location);
        $level->min_quantity = (string) $min;
        $level->max_quantity = (string) $max;
        $level->save();

        return $level->refresh();
    }

    /**
     * Przeliczenie projekcji z rejestru od zera.
     *
     * Projekcja ma być w każdej chwili odtwarzalna z ruchów — inaczej
     * jest drugim źródłem prawdy, a wtedy rozjazd między nią a sumą
     * przestaje być wykrywalny. Stąd ta metoda: i jako narzędzie
     * naprawcze, i jako dowód w teście.
     */
    public function rebuild(Product $product, ?Location $location = null): StockLevel
    {
        $level = $this->level($product, $location);

        $physical = 0.0;
        $reserved = 0.0;

        /** @var iterable<StockMovement> $movements */
        $movements = StockMovement::query()
            ->where('product_id', $product->getKey())
            ->where('location_id', $level->location_id)
            ->orderBy('id')
            ->get();

        foreach ($movements as $movement) {
            $signed = $movement->type->sign() * (float) $movement->quantity;

            if ($movement->type->touchesPhysical()) {
                $physical += $signed;

                continue;
            }

            $reserved += $signed;
        }

        $level->quantity = (string) round($physical, 3);
        $level->reserved = (string) round($reserved, 3);
        $level->save();

        return $level->refresh();
    }

    /** Wiersz projekcji dla produktu i lokalizacji; zakłada brakujący. */
    public function level(Product $product, ?Location $location = null): StockLevel
    {
        $locationId = $location?->getKey() ?? $this->defaultLocationId();

        /** @var StockLevel */
        return StockLevel::query()->firstOrCreate(
            ['product_id' => (int) $product->getKey(), 'location_id' => $locationId],
            ['product_id' => (int) $product->getKey(), 'location_id' => $locationId],
        );
    }

    private function record(
        StockMovementType $type,
        Product $product,
        float $quantity,
        ?Location $location,
        ?int $orderId = null,
        ?string $document = null,
        ?string $note = null,
    ): StockLevel {
        if ($quantity === 0.0) {
            return $this->level($product, $location);
        }

        if ($type !== StockMovementType::CORRECTION && $quantity < 0) {
            throw new RuntimeException('Ilość ruchu magazynowego nie może być ujemna — kierunek niesie typ.');
        }

        return DB::transaction(function () use ($type, $product, $quantity, $location, $orderId, $document, $note): StockLevel {
            $level = $this->level($product, $location);

            StockMovement::query()->create([
                'product_id' => (int) $product->getKey(),
                'location_id' => $level->location_id,
                'type' => $type->value,
                'quantity' => (string) $quantity,
                'order_id' => $orderId,
                'document' => $document ?? $type->document(),
                'note' => $note,
                'created_by' => Auth::id(),
            ]);

            $signed = $type->sign() * $quantity;
            $column = $type->touchesPhysical() ? 'quantity' : 'reserved';

            $level->{$column} = (string) round((float) $level->{$column} + $signed, 3);
            $level->save();

            return $level->refresh();
        });
    }

    private function defaultLocationId(): ?int
    {
        /** @var Location|null $location */
        $location = Location::query()->where('is_default', true)->first();

        return $location === null ? null : (int) $location->getKey();
    }
}
