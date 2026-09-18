<?php

declare(strict_types=1);

namespace Tests\Feature\Pricing;

use Tests\TestCase;
use App\Enum\Section;
use App\Models\Product;
use App\Models\PriceSection;
use App\Enum\PriceUnavailableReason;
use Database\Seeders\Core\RoleSeeder;
use PHPUnit\Framework\Attributes\Test;
use Database\Seeders\Dev\GlassPriceSeeder;
use App\Services\Pricing\PriceResolver;
use Database\Seeders\Core\GlassCatalogSeeder;
use Database\Seeders\Core\PriceSectionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Macierz cennika szkła w danych deweloperskich.
 *
 * Seeder jest deweloperski, ale cicha awaria kosztuje godzinę: pusta
 * macierz wygląda w aplikacji dokładnie tak samo jak dobrze policzona
 * formatka bez ceny, a dopłaty za kształt i gabaryt przestają cokolwiek
 * robić, bo nie mają czego mnożyć.
 */
class GlassPriceSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        (new RoleSeeder())->run();
        (new GlassCatalogSeeder())->run();
        (new PriceSectionSeeder())->run();
    }

    #[Test]
    public function float_dostaje_cene_z_udokumentowanego_wspolczynnika(): void
    {
        (new GlassPriceSeeder())->run();

        /** @var Product $glass */
        $glass = Product::query()->where('name', 'float 2mm')->firstOrFail();

        $price = (new PriceResolver())->catalogue($glass, $this->section('Detaliczny extra'));

        // 22,00 zł/m² × 3,8 — para zweryfikowana w 50-cennik.md par. 4.
        $this->assertTrue($price->isAvailable());
        $this->assertSame('83.60', (string) $price->netPrice);
    }

    #[Test]
    public function kazde_szklo_z_katalogu_ma_komorke_w_kazdej_sekcji(): void
    {
        (new GlassPriceSeeder())->run();

        $sections = PriceSection::query()
            ->where('section', Section::GLASS->value)
            ->count();

        /** @var iterable<Product> $products */
        $products = Product::query()->where('section', Section::GLASS->value)->get();

        $resolver = new PriceResolver();

        foreach ($products as $product) {
            $price = $resolver->catalogue($product, $this->section('Detaliczny podstawowy'));

            // Komórka ma powstać nawet bez ceny zakupu. Wtedy cena
            // nadal jest niedostępna, ale z innego powodu — i ten powód
            // wskazuje, czego brakuje: ceny zakupu, a nie cennika.
            $this->assertNotSame(
                PriceUnavailableReason::NO_PRICE_LIST_ITEM,
                $price->unavailableReason,
                sprintf('%s nie ma pozycji cennika', $product->name),
            );
        }

        $this->assertSame(6, $sections);
    }

    private function section(string $name): PriceSection
    {
        /** @var PriceSection */
        return PriceSection::query()
            ->where('section', Section::GLASS->value)
            ->where('name', $name)
            ->firstOrFail();
    }
}
