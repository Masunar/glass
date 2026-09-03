<?php

declare(strict_types=1);

namespace Tests\Feature\Dictionary;

use Tests\TestCase;
use App\Enum\Section;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Location;
use App\Models\Workstation;
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
 *
 * Testy nie zakładają pustych tabel. `migrate:fresh --seed` odpala się
 * raz na cały przebieg i poza transakcją, więc słowniki referencyjne
 * (flota, typy faktur, sekcje cenowe) mogą już być wypełnione — nazwy
 * używane niżej są spoza seedera, a asercje dotyczą wierszy założonych
 * w samym teście.
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
    public function kazde_zrodlo_listy_wyboru_daje_etykiety(): void
    {
        /** @var User $user */
        $user = User::query()->create([
            'first_name' => 'Marek',
            'last_name' => 'Borowski',
            'email' => 'marek@example.test',
            'password' => 'x',
            'is_active' => true,
        ]);

        // Nie każdy model, na który wskazuje słownik, ma kolumnę `name`
        // — użytkownik ma imię i nazwisko. Ten test przechodzi po
        // wszystkich źródłach z rejestru, żeby żadne nie odpadło po cichu
        // przy dołożeniu nowego pola wskazującego.
        // Stanowiska nie mają seedera — słowniki referencyjne są tu
        // zakładane wprost, żeby test nie zależał od tego, co akurat
        // wypełnił `migrate:fresh --seed`.
        Workstation::query()->firstOrCreate(['name' => 'Stół do cięcia'], [
            'name' => 'Stół do cięcia',
            'is_active' => true,
            'position' => 10,
        ]);

        $sources = [];

        foreach ((new DictionaryRegistry())->all() as $definition) {
            foreach ($definition->fields as $field) {
                if ($field->source !== null) {
                    $sources[$field->source] = true;
                }
            }
        }

        $this->assertNotEmpty($sources);

        foreach (array_keys($sources) as $source) {
            $options = $this->service->optionsFor($source);

            $this->assertNotEmpty($options, sprintf('Źródło %s nie zwróciło nic.', $source));

            foreach ($options as $option) {
                $this->assertNotSame('', $option['label'], sprintf('Pusta etykieta w źródle %s.', $source));
            }
        }

        $this->assertContains(
            'Marek Borowski',
            array_column($this->service->optionsFor('users'), 'label'),
        );

        $this->assertNotNull($user->getKey());
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
        $first = $this->service->save('vehicles', ['name' => 'Star 266', 'payload_kg' => 850]);
        $this->assertSame([], $first['errors']);

        $second = $this->service->save('vehicles', ['name' => 'Star 266', 'payload_kg' => 900]);

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
        $first = $this->service->save('vehicles', ['name' => 'Star 266', 'payload_kg' => 1200]);
        $second = $this->service->save('vehicles', ['name' => 'Żuk A11', 'payload_kg' => 700]);

        $this->assertSame([], $first['errors']);
        $this->assertSame([], $second['errors']);

        $this->assertGreaterThan(
            Vehicle::query()->findOrFail($first['id'])->position,
            Vehicle::query()->findOrFail($second['id'])->position,
        );
    }

    #[Test]
    public function slownik_nie_usuwa_tylko_dezaktywuje(): void
    {
        $created = $this->service->save('vehicles', ['name' => 'Nysa 522', 'payload_kg' => 1100]);
        $this->assertSame([], $created['errors']);

        $before = Vehicle::query()->count();

        $this->service->deactivate('vehicles', (int) $created['id']);

        $this->assertSame($before, Vehicle::query()->count());
        $this->assertFalse(Vehicle::query()->findOrFail($created['id'])->is_active);

        $active = array_column($this->service->rows('vehicles'), 'name');
        $this->assertNotContains('Nysa 522', $active);

        $all = array_column($this->service->rows('vehicles', includeInactive: true), 'name');
        $this->assertContains('Nysa 522', $all);
    }

    #[Test]
    public function wiersz_niesie_etykiete_wskazanej_pozycji(): void
    {
        /** @var Location $location */
        $location = Location::query()->orderBy('position')->firstOrFail();
        $locationId = (int) $location->getKey();

        $created = $this->service->save('vehicles', [
            'name' => 'Star 266',
            'payload_kg' => 850,
            'location_id' => $locationId,
        ]);
        $this->assertSame([], $created['errors']);

        $row = $this->rowById('vehicles', (int) $created['id']);

        $this->assertSame($locationId, $row['location_id']);
        $this->assertSame($location->name, $row['location_id_label']);
    }

    /**
     * @return array<string, mixed>
     */
    private function rowById(string $slug, int $id): array
    {
        foreach ($this->service->rows($slug, includeInactive: true) as $row) {
            if ($row['id'] === $id) {
                return $row;
            }
        }

        $this->fail(sprintf('Brak wiersza %d w słowniku %s.', $id, $slug));
    }

    #[Test]
    public function edycja_zapisuje_tylko_przeslane_pola(): void
    {
        $created = $this->service->save('vehicles', [
            'name' => 'Star 266',
            'payload_kg' => 850,
            'crew_slots' => 2,
        ]);
        $this->assertSame([], $created['errors']);

        $updated = $this->service->save('vehicles', ['name' => 'Star 266 skrzyniowy'], (int) $created['id']);
        $this->assertSame([], $updated['errors']);

        $vehicle = Vehicle::query()->findOrFail($created['id']);

        $this->assertSame('Star 266 skrzyniowy', $vehicle->name);
        $this->assertSame(850, $vehicle->payload_kg);
        $this->assertSame(2, $vehicle->crew_slots);
    }

    #[Test]
    public function edycja_czastkowa_nie_gubi_zakresu_unikalnosci(): void
    {
        (new PriceSectionSeeder())->run();

        /** @var PriceSection $section */
        $section = PriceSection::query()
            ->where('section', Section::GLASS->value)
            ->where('name', 'Detaliczny podstawowy')
            ->firstOrFail();

        // Sama zmiana flagi, bez odsyłania sekcji asortymentu — wiersz
        // nie może zderzyć się sam ze sobą.
        $result = $this->service->save(
            'price-sections',
            ['name' => 'Detaliczny podstawowy', 'is_active' => true],
            (int) $section->getKey(),
        );

        $this->assertSame([], $result['errors']);
    }

    #[Test]
    public function nieznany_slownik_konczy_sie_404(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);

        $this->service->rows('nie-ma-takiego');
    }
}
