<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use Tests\TestCase;
use App\Enum\Unit;
use App\Enum\Section;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\ProductGroup;
use App\Services\DictionaryService;
use PHPUnit\Framework\Attributes\Test;
use App\Dictionaries\DictionaryRegistry;
use Database\Seeders\Dev\FittingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Dostawcy jako słownik i przypięcie produktu do dostawcy.
 */
class SupplierTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function dostawcy_sa_slownikiem_prostym(): void
    {
        $service = new DictionaryService(new DictionaryRegistry());

        $slugs = array_column($service->describe(), 'slug');

        $this->assertContains('suppliers', $slugs);
    }

    #[Test]
    public function produkt_bez_dostawcy_jest_poprawny(): void
    {
        // Wiekszosc katalogu nie ma dzis przypisanego dostawcy i to nie
        // jest blad danych — to brak, ktory ma prawo istniec.
        $product = $this->product('BEZ-DOSTAWCY');

        $this->assertNull($product->supplier_id);
        $this->assertNull($product->supplier);
    }

    #[Test]
    public function produkt_wskazuje_dostawce(): void
    {
        /** @var Supplier $supplier */
        $supplier = Supplier::query()->create(['name' => 'Testowy dostawca']);

        $product = $this->product('Z-DOSTAWCA');
        $product->supplier_id = $supplier->id;
        $product->save();

        $this->assertSame('Testowy dostawca', $product->fresh()?->supplier?->name);
    }

    #[Test]
    public function skasowanie_dostawcy_nie_kasuje_produktu(): void
    {
        // Produkt zostaje w katalogu i w cenniku; znika tylko wiedza
        // o tym, od kogo go kupowalismy. Kaskada zabralaby ze soba
        // historie zlecen.
        /** @var Supplier $supplier */
        $supplier = Supplier::query()->create(['name' => 'Do skasowania']);

        $product = $this->product('SIEROTA');
        $product->supplier_id = $supplier->id;
        $product->save();

        $supplier->delete();

        $fresh = $product->fresh();

        $this->assertNotNull($fresh);
        $this->assertNull($fresh->supplier_id);
    }

    #[Test]
    public function seeder_deweloperski_przypina_okucia_do_cda(): void
    {
        $this->seed(FittingSeeder::class);

        /** @var Product|null $fitting */
        $fitting = Product::query()
            ->where('section', Section::FITTINGS->value)
            ->where('code', 'TGHU90LH BL')
            ->first();

        $this->assertNotNull($fitting);
        $this->assertSame('CDA', $fitting->supplier?->name);
    }

    private function product(string $code): Product
    {
        /** @var ProductGroup $group */
        $group = ProductGroup::query()->firstOrCreate(
            ['section' => Section::FITTINGS->value, 'name' => 'Grupa testowa'],
            ['section' => Section::FITTINGS->value, 'name' => 'Grupa testowa', 'position' => 90],
        );

        /** @var Product */
        return Product::query()->create([
            'product_group_id' => $group->id,
            'section' => Section::FITTINGS->value,
            'code' => $code,
            'name' => 'Produkt ' . $code,
            'unit' => Unit::PIECE->value,
            'vat_rate' => 23,
        ]);
    }
}
