<?php

declare(strict_types=1);

namespace Tests\Feature\Dictionary;

use Tests\TestCase;
use App\Enum\Section;
use App\Enum\AddressKind;
use App\Models\Contractor;
use App\Models\PriceSection;
use App\Enum\ContractorType;
use App\Services\ContractorService;
use Database\Seeders\Core\RoleSeeder;
use Database\Seeders\Core\PriceSectionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Kartoteka kontrahentów.
 *
 * Testy pilnują przede wszystkim jakości danych: stara baza jest pełna
 * telefonów „0" i e-maili „123" wpisanych po to, żeby formularz
 * przepuścił, a duplikaty tej samej firmy rozbijają historię klienta.
 */
class ContractorServiceTest extends TestCase
{
    use RefreshDatabase;

    private ContractorService $service;

    protected function setUp(): void
    {
        parent::setUp();

        (new RoleSeeder())->run();
        (new PriceSectionSeeder())->run();

        $this->service = new ContractorService();
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function company(array $overrides = []): array
    {
        return array_merge([
            'type' => ContractorType::COMPANY->value,
            'name' => 'STECKO MEBLE sp. z o.o. sp.k.',
            'short_name' => 'Stecko Meble',
            'tax_id' => '8522347066',
        ], $overrides);
    }

    public function test_firma_zapisuje_sie_z_adresem(): void
    {
        $result = $this->service->save($this->company([
            'address' => [
                'city' => 'Szczecin',
                'postal_code' => '70-344',
                'street' => 'Chodkiewicza',
                'building_number' => '2',
                'unit_number' => '3a',
            ],
        ]));

        $this->assertSame([], $result['errors']);

        /** @var Contractor $contractor */
        $contractor = Contractor::query()->findOrFail($result['id']);

        $this->assertSame(ContractorType::COMPANY, $contractor->type);
        $this->assertSame(
            'Chodkiewicza 2/3a, 70-344 Szczecin',
            $contractor->addressOf(AddressKind::REGISTERED)?->oneLine(),
        );
    }

    public function test_nip_zapisuje_sie_bez_myslnikow(): void
    {
        $result = $this->service->save($this->company(['tax_id' => '852-234-70-66']));

        $this->assertSame([], $result['errors']);
        $this->assertSame('8522347066', Contractor::query()->findOrFail($result['id'])->tax_id);
    }

    public function test_nip_z_bledna_suma_kontrolna_nie_przechodzi(): void
    {
        $result = $this->service->save($this->company(['tax_id' => '8522347067']));

        $this->assertArrayHasKey('tax_id', $result['errors']);
    }

    public function test_same_zera_nie_sa_numerem(): void
    {
        $result = $this->service->save($this->company(['tax_id' => '0000000000']));

        $this->assertArrayHasKey('tax_id', $result['errors']);
    }

    public function test_ten_sam_nip_nie_moze_sie_powtorzyc(): void
    {
        $this->service->save($this->company());
        $result = $this->service->save($this->company(['name' => 'Ta sama firma raz jeszcze']));

        $this->assertArrayHasKey('tax_id', $result['errors']);
        $this->assertSame(1, Contractor::query()->count());
    }

    public function test_firma_bez_nipu_nie_przechodzi(): void
    {
        $result = $this->service->save($this->company(['tax_id' => null]));

        $this->assertArrayHasKey('tax_id', $result['errors']);
    }

    public function test_osoba_prywatna_nie_potrzebuje_nipu(): void
    {
        $result = $this->service->save([
            'type' => ContractorType::PERSON->value,
            'name' => 'Jarosław Kaczocha',
            'first_name' => 'Jarosław',
            'last_name' => 'Kaczocha',
        ]);

        $this->assertSame([], $result['errors']);
    }

    public function test_smieciowy_telefon_nie_przechodzi(): void
    {
        foreach (['0', '123'] as $junk) {
            $result = $this->service->save([
                'type' => ContractorType::PERSON->value,
                'name' => 'Klient testowy',
                'phone' => $junk,
            ]);

            $this->assertArrayHasKey('phone', $result['errors'], sprintf('telefon "%s"', $junk));
        }
    }

    public function test_pusty_telefon_przechodzi(): void
    {
        $result = $this->service->save($this->company(['phone' => '', 'email' => '']));

        $this->assertSame([], $result['errors']);
        $this->assertNull(Contractor::query()->findOrFail($result['id'])->phone);
    }

    public function test_smieciowy_email_nie_przechodzi(): void
    {
        $result = $this->service->save($this->company(['email' => '123']));

        $this->assertArrayHasKey('email', $result['errors']);
    }

    public function test_przypisanie_sekcji_cenowej(): void
    {
        $id = (int) $this->service->save($this->company())['id'];

        $section = PriceSection::query()
            ->where('section', Section::GLASS->value)
            ->where('name', 'Biznesowy strefa 3')
            ->firstOrFail();

        $errors = $this->service->savePriceSections($id, [Section::GLASS->value => $section->id]);

        $this->assertSame([], $errors);

        /** @var Contractor $contractor */
        $contractor = Contractor::query()->findOrFail($id);

        $this->assertSame(
            'Biznesowy strefa 3',
            $contractor->priceSectionFor(Section::GLASS)?->name,
        );
    }

    public function test_sekcja_cenowa_musi_pasowac_do_sekcji_asortymentu(): void
    {
        $id = (int) $this->service->save($this->company())['id'];

        $services = PriceSection::query()
            ->where('section', Section::SERVICES->value)
            ->firstOrFail();

        $errors = $this->service->savePriceSections($id, [Section::GLASS->value => $services->id]);

        $this->assertArrayHasKey(Section::GLASS->value, $errors);
    }

    public function test_wyczyszczenie_przypisania_wraca_do_domyslnej(): void
    {
        $id = (int) $this->service->save($this->company())['id'];

        $section = PriceSection::query()
            ->where('section', Section::GLASS->value)
            ->where('name', 'Biznesowy strefa 3')
            ->firstOrFail();

        $this->service->savePriceSections($id, [Section::GLASS->value => $section->id]);
        $this->service->savePriceSections($id, [Section::GLASS->value => null]);

        /** @var Contractor $contractor */
        $contractor = Contractor::query()->findOrFail($id);

        $this->assertNull($contractor->priceSectionFor(Section::GLASS));
    }

    public function test_karta_pokazuje_sekcje_domyslna_gdy_brak_przypisania(): void
    {
        $id = (int) $this->service->save($this->company())['id'];

        $card = $this->service->card($id);
        $glass = collect($card['price_sections'])->firstWhere('section', Section::GLASS->value);

        $this->assertNotNull($glass);
        $this->assertNull($glass['price_section_id']);
        $this->assertSame('Detaliczny podstawowy', $glass['default_name']);
    }

    public function test_szukanie_po_nazwie_i_nipie(): void
    {
        $this->service->save($this->company());
        $this->service->save([
            'type' => ContractorType::PERSON->value,
            'name' => 'Jarosław Kaczocha',
        ]);

        $this->assertCount(1, $this->service->list('Stecko')['contractors']);
        $this->assertCount(1, $this->service->list('8522')['contractors']);
        // Ponizej trzech znakow szukanie nie zawęza - zwraca cala liste.
        $this->assertCount(2, $this->service->list('St')['contractors']);
    }
}
