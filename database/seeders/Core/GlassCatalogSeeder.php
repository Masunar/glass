<?php

declare(strict_types=1);

namespace Database\Seeders\Core;

use Carbon\Carbon;
use App\Enum\Unit;
use App\Models\Product;
use App\Enum\Section;
use App\Models\ProductGlass;
use App\Models\ProductGroup;
use App\Models\PurchasePrice;
use Salvon\Database\Seeder;
use App\Enum\PurchasePriceSource;

/**
 * Grupy szkła i materiały wyjściowe.
 *
 * Ceny zakupu float odtworzone z macierzy cennika starego systemu przez
 * podzielenie ceny obowiązującej przez współczynnik narzutu, a następnie
 * potwierdzone co do grosza wartościami odczytanymi wprost ze słownika
 * materiałów. To jedyne miejsce, gdzie liczby z dokumentacji wchodzą
 * do kodu jako dane wyjściowe, a nie jako przykład.
 */
class GlassCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            'FLOAT', 'OPTIWHITE', 'SZKŁO CHIŃSKIE', 'LUSTRA CHIŃSKIE', 'LUSTRA',
            'ANTISOL', 'REFLEKSYJNE', 'SATYNOWE', 'SZKŁO Z GRAFIKĄ',
            'SZKŁO LAKIEROWANE', 'AGC LACOBEL', 'VSG', 'VSG/ESG PVB',
            'SZYBY ZESPOLONE', 'ORNAMENT', 'LUSTRA MOZAIKA', 'INNE',
        ];

        $position = 0;

        foreach ($groups as $name) {
            ProductGroup::query()->firstOrCreate(
                ['section' => Section::GLASS->value, 'name' => $name],
                ['section' => Section::GLASS->value, 'name' => $name, 'position' => $position += 10],
            );
        }

        $float = ProductGroup::query()
            ->where('section', Section::GLASS->value)
            ->where('name', 'FLOAT')
            ->firstOrFail();

        // grubosc w mm => cena zakupu netto za m2
        $floatPrices = [
            '2' => '22.00',
            '3' => '20.00',
            '4' => '25.00',
            '5' => '31.00',
            '6' => '37.00',
            '8' => '52.00',
            '10' => '65.00',
            '12' => '91.00',
            '15' => '196.00',
        ];

        foreach ($floatPrices as $thickness => $price) {
            $this->createGlassProduct($float, sprintf('float %dmm', $thickness), (float) $thickness, $price);
        }

        $mirrors = ProductGroup::query()
            ->where('section', Section::GLASS->value)
            ->where('name', 'LUSTRA')
            ->firstOrFail();

        // Cena zakupu luster nie jest udokumentowana - produkt powstaje
        // bez niej, zeby nie wprowadzac do systemu zmyslonej liczby.
        $this->createGlassProduct($mirrors, 'lustro AGC 4mm', 4.0, null);
        $this->createGlassProduct($mirrors, 'lustro AGC Clearvision 4mm', 4.0, null);
    }

    private function createGlassProduct(ProductGroup $group, string $name, float $thickness, ?string $purchasePrice): void
    {
        /** @var Product $product */
        $product = Product::query()->firstOrCreate(
            ['section' => Section::GLASS->value, 'name' => $name],
            [
                'product_group_id' => $group->id,
                'section' => Section::GLASS->value,
                'name' => $name,
                'unit' => Unit::SQUARE_METER->value,
                'vat_rate' => 23,
            ],
        );

        ProductGlass::query()->firstOrCreate(
            ['product_id' => $product->id],
            ['product_id' => $product->id, 'thickness_mm' => $thickness],
        );

        if ($purchasePrice === null) {
            return;
        }

        PurchasePrice::query()->firstOrCreate(
            ['product_id' => $product->id, 'valid_from' => Carbon::today()->startOfYear()],
            [
                'product_id' => $product->id,
                'net_price' => $purchasePrice,
                'source' => PurchasePriceSource::MANUAL->value,
                'valid_from' => Carbon::today()->startOfYear(),
            ],
        );
    }
}
