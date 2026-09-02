<?php

declare(strict_types=1);

namespace Tests\Feature\Dictionary;

use Tests\TestCase;
use App\Enum\Section;
use App\Models\Product;
use App\Models\ProductGlass;
use App\Models\PriceListItem;
use Database\Seeders\Core\GlassCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Wartości w tych testach nie są wymyślone.
 *
 * Ceny zakupu float odtworzono z macierzy cennika starego systemu przez
 * podzielenie ceny obowiązującej przez współczynnik narzutu, a potem
 * potwierdzono co do grosza odczytem ze słownika materiałów. Waga
 * jednostkowa zgadzała się w każdym wierszu tamtego słownika.
 *
 * To znaczy, że te asercje nie sprawdzają zgodności kodu z samym sobą,
 * tylko zgodność z zachowaniem systemu, który ten kod ma zastąpić.
 */
class GlassCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        (new GlassCatalogSeeder())->run();
    }

    public function test_waga_metra_kwadratowego_to_dwa_i_pol_raza_grubosc(): void
    {
        $panes = ProductGlass::query()->get();

        $this->assertNotEmpty($panes);

        foreach ($panes as $glass) {
            $this->assertEqualsWithDelta(
                $glass->thickness_mm * 2.5,
                $glass->weightPerM2(),
                0.001,
                sprintf('Waga dla grubości %s mm nie zgadza się z gęstością szkła.', $glass->thickness_mm),
            );
        }
    }

    /**
     * Schemat „CAPS L4AGC1296x796" ze słownika starego systemu: lustro
     * AGC 4 mm o wymiarach 1296 × 796 mm ma wagę 10,32 kg.
     */
    public function test_waga_formatki_zgadza_sie_z_udokumentowanym_schematem(): void
    {
        $mirror = Product::query()
            ->where('section', Section::GLASS->value)
            ->where('name', 'lustro AGC 4mm')
            ->firstOrFail();

        $this->assertSame(10.32, $mirror->glass->weightOfPane(1296, 796));
    }

    #[DataProvider('cenyZakupuFloat')]
    public function test_ceny_zakupu_float_zgadzaja_sie_ze_slownikiem(string $name, string $expected): void
    {
        $product = Product::query()
            ->where('section', Section::GLASS->value)
            ->where('name', $name)
            ->firstOrFail();

        $this->assertSame($expected, $product->purchasePriceAt()?->net_price);
    }

    /** @return array<string, array{string, string}> */
    public static function cenyZakupuFloat(): array
    {
        return [
            'float 2mm' => ['float 2mm', '22.00'],
            'float 3mm' => ['float 3mm', '20.00'],
            'float 4mm' => ['float 4mm', '25.00'],
            'float 5mm' => ['float 5mm', '31.00'],
            'float 6mm' => ['float 6mm', '37.00'],
            'float 8mm' => ['float 8mm', '52.00'],
            'float 10mm' => ['float 10mm', '65.00'],
            'float 12mm' => ['float 12mm', '91.00'],
            'float 15mm' => ['float 15mm', '196.00'],
        ];
    }

    /**
     * Wzór cennika: cena obowiązująca = cena zakupu × współczynnik narzutu.
     * Wszystkie pary odczytane z macierzy cennika starego systemu.
     */
    #[DataProvider('macierzCennika')]
    public function test_cena_to_cena_zakupu_razy_wspolczynnik(string $purchase, string $coefficient, string $expected): void
    {
        $this->assertSame($expected, PriceListItem::computePrice($purchase, $coefficient));
    }

    /** @return list<array{string, string, string}> */
    public static function macierzCennika(): array
    {
        return [
            ['22.00', '3.8000', '83.60'],
            ['22.00', '3.3000', '72.60'],
            ['22.00', '2.1000', '46.20'],
            ['20.00', '3.8000', '76.00'],
            ['20.00', '2.4000', '48.00'],
            ['25.00', '3.0000', '75.00'],
            ['25.00', '2.2000', '55.00'],
            ['31.00', '3.0000', '93.00'],
            ['31.00', '2.8000', '86.80'],
            ['31.00', '2.2000', '68.20'],
            ['37.00', '5.0000', '185.00'],
            ['37.00', '3.5000', '129.50'],
            ['37.00', '2.6000', '96.20'],
            ['52.00', '5.0000', '260.00'],
            ['52.00', '2.6000', '135.20'],
            ['52.00', '2.2000', '114.40'],
            ['65.00', '4.0000', '260.00'],
            ['65.00', '2.6000', '169.00'],
            ['65.00', '2.2000', '143.00'],
        ];
    }

    public function test_lustra_nie_dostaly_zmyslonej_ceny_zakupu(): void
    {
        $mirror = Product::query()
            ->where('section', Section::GLASS->value)
            ->where('name', 'lustro AGC 4mm')
            ->firstOrFail();

        $this->assertNull(
            $mirror->purchasePriceAt(),
            'Cena zakupu luster nie jest udokumentowana i nie wolno jej zgadywać.',
        );
    }
}
