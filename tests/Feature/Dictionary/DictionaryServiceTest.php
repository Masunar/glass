<?php

declare(strict_types=1);

namespace Tests\Feature\Dictionary;

use Tests\TestCase;
use App\Enum\Section;
use App\Models\Vehicle;
use App\Models\Location;
use App\Models\InvoiceType;
use App\Models\PriceSection;
use App\Services\DictionaryService;
use Database\Seeders\Core\RoleSeeder;
use PHPUnit\Framework\Attributes\Test;
use App\Dictionaries\DictionaryRegistry;
use Database\Seeders\Core\LocationSeeder;
use Database\Seeders\Core\PriceSectionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Słowniki proste: reguły wspólne dla wszystkich zakładek.
 */
class DictionaryServiceTest extends TestCase
{
    use RefreshDatabase;

    private DictionaryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        (new RoleSeeder())->run();
        (new LocationSeeder())->run();

        $this->service = new DictionaryService(new DictionaryRegistry());
    }

    #[Test]
    public function opis_slownikow_niesie_pola_i_listy_wyboru(): void
    {
        $described = $this->service->describe();
        $slugs = array_column($described, 'slug');

        $this->assertContains('locations', $slugs);
        $this->assertContains('vehicles', $slugs);

        $payload = $this->field('vehicles', 'payload_kg');

        $this->assertSame('integer', $payload['type']);
        $this->assertTrue($payload['required']);

        // Lista lokalizacji budowana w locie, a nie zaszyta w definicji.
        $this->assertNotEmpty($this->field('vehicles', 'location_id')['options']);
    }

    /**
     * @return array<string, mixed>
     */
    private function field(string $slug, string $key): array
    {
        foreach ($this->service->describe() as $dictionary) {
            if ($dictionary['slug'] !== $slug) {
                continue;
            }

            /** @var list<array<string, mixed>> $fields */
            $fields = $dictionary['fields'];

            foreach ($fields as $field) {
                if ($field['key'] === $key) {
                    return $field;
                }
            }
        }

        $this->fail(sprintf('Brak pola %s w słowniku %s.', $key, $slug));
    }

    #[Test]
    public function nazwa_jest_wymagana(): void
    {
        $result = $this->service->save('vehicles', ['payload_kg' => 800]);

        $this->assertArrayHasKey('name', $result['errors']);
        $this->assertNull($result['id']);
    }

    #[Test]
    public function nazwa_jest_unikalna(): void
    {
        $first = $this->service->save('vehicles', ['name' => 'Mercedes', 'payload_kg' => 850]);
        $this->assertSame([], $first['errors']);

        $second = $this->service->save('vehicles', ['name' => 'Mercedes', 'payload_kg' => 900]);

        $this->assertArrayHasKey('name', $second['errors']);
    }

    #[Test]
    public function unikalnosc_nazwy_moze_byc_zawezona_do_sekcji(): void
    {
        (new PriceSectionSeeder())->run();

        // „Detaliczny podstawowy” istnieje w szkle i w okuciach naraz —
        // unikalność sekcji cenowych obowiązuje w obrębie sekcji.
        $result = $this->service->save('price-sections', [
            'section' => Section::GLASS->value,
            'name' => 'Detaliczny podstawowy',
        ]);

        $this->assertArrayHasKey('name', $result['errors']);

        $other = $this->service->save('price-sections', [
            'section' => Section::GLASS->value,
            'name' => 'Nowy poziom',
        ]);

        $this->assertSame([], $other['errors']);
    }

    #[Test]
    public function pozycja_domyslna_jest_jedna_w_obrebie_zakresu(): void
    {
        (new PriceSectionSeeder())->run();

        /** @var PriceSection $previous */
        $previous = PriceSection::query()
            ->where('section', Section::GLASS->value)
            ->where('is_default', true)
            ->firstOrFail();

        $result = $this->service->save('price-sections', [
            'section' => Section::GLASS->value,
            'name' => 'Nowy domyślny',
            'is_default' => true,
        ]);

        $this->assertSame([], $result['errors']);

        /** @var PriceSection $refreshed */
        $refreshed = PriceSection::query()->findOrFail($previous->getKey());

        $this->assertFalse($refreshed->is_default);

        // Domyślna sekcja okuć nie została ruszona — zakres to sekcja.
        $this->assertSame(1, PriceSection::query()
            ->where('section', Section::FITTINGS->value)
            ->where('is_default', true)
            ->count());
    }

    #[Test]
    public function pozycja_domyslna_bez_zakresu_obowiazuje_w_calym_slowniku(): void
    {
        $first = $this->service->save('invoice-types', [
            'name' => 'Faktura VAT',
            'vat_rate' => 23,
            'is_default' => true,
        ]);
        $this->assertSame([], $first['errors']);

        $second = $this->service->save('invoice-types', [
            'name' => 'Paragon',
            'vat_rate' => 23,
            'is_default' => true,
        ]);
        $this->assertSame([], $second['errors']);

        $this->assertSame(1, InvoiceType::query()->where('is_default', true)->count());
        $this->assertSame('Paragon', InvoiceType::query()->where('is_default', true)->value('name'));
    }

    #[Test]
    public function nowy_wiersz_dostaje_pozycje_na_koncu_listy(): void
    {
        $first = $this->service->save('vehicles', ['name' => 'Fiat', 'payload_kg' => 1200]);
        $second = $this->service->save('vehicles', ['name' => 'Ford', 'payload_kg' => 700]);

        $this->assertGreaterThan(
            Vehicle::query()->findOrFail($first['id'])->position,
            Vehicle::query()->findOrFail($second['id'])->position,
        );
    }

    #[Test]
    public function slownik_nie_usuwa_tylko_dezaktywuje(): void
    {
        $created = $this->service->save('vehicles', ['name' => 'Hyundai', 'payload_kg' => 1100]);

        $this->service->deactivate('vehicles', (int) $created['id']);

        $this->assertSame(1, Vehicle::query()->count());
        $this->assertFalse(Vehicle::query()->findOrFail($created['id'])->is_active);

        $active = array_column($this->service->rows('vehicles'), 'name');
        $this->assertNotContains('Hyundai', $active);

        $all = array_column($this->service->rows('vehicles', includeInactive: true), 'name');
        $this->assertContains('Hyundai', $all);
    }

    #[Test]
    public function wiersz_niesie_etykiete_wskazanej_pozycji(): void
    {
        /** @var Location $location */
        $location = Location::query()->orderBy('position')->firstOrFail();
        $locationId = (int) $location->getKey();

        $this->service->save('vehicles', [
            'name' => 'Mercedes',
            'payload_kg' => 850,
            'location_id' => $locationId,
        ]);

        $row = $this->service->rows('vehicles')[0];

        $this->assertSame($locationId, $row['location_id']);
        $this->assertSame($location->name, $row['location_id_label']);
    }

    #[Test]
    public function edycja_zapisuje_tylko_przeslane_pola(): void
    {
        $created = $this->service->save('vehicles', [
            'name' => 'Mercedes',
            'payload_kg' => 850,
            'crew_slots' => 2,
        ]);

        $updated = $this->service->save('vehicles', ['name' => 'Mercedes Sprinter'], (int) $created['id']);
        $this->assertSame([], $updated['errors']);

        $vehicle = Vehicle::query()->findOrFail($created['id']);

        $this->assertSame('Mercedes Sprinter', $vehicle->name);
        $this->assertSame(850, $vehicle->payload_kg);
        $this->assertSame(2, $vehicle->crew_slots);
    }

    #[Test]
    public function nieznany_slownik_konczy_sie_404(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);

        $this->service->rows('nie-ma-takiego');
    }
}
