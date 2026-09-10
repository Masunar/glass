<?php

declare(strict_types=1);

namespace Tests\Feature\Dictionary;

use Carbon\Carbon;
use Tests\TestCase;
use App\Models\AuditEntry;
use App\Models\GlobalParameter;
use App\Services\GlobalParameterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\Core\GlobalParameterSeeder;

/**
 * Zmiana parametru wyceny zmienia ceny wszystkich nowych ofert, więc
 * zapis musi być wersjonowany i zostawiać ślad. Te testy pilnują obu
 * rzeczy — w starym systemie nie było ani jednej, ani drugiej.
 */
class GlobalParameterServiceTest extends TestCase
{
    use RefreshDatabase;

    private GlobalParameterService $service;

    protected function setUp(): void
    {
        parent::setUp();

        (new GlobalParameterSeeder())->run();

        $this->service = new GlobalParameterService();
    }

    public function test_zmiana_zamyka_poprzednia_wersje_i_zaklada_nowa(): void
    {
        $before = GlobalParameter::query()->where('key', 'shape_surcharge_percent')->firstOrFail();
        $this->assertNull($before->valid_to);

        $errors = $this->service->update(['shape_surcharge_percent' => '40']);

        $this->assertSame([], $errors);

        $versions = GlobalParameter::query()
            ->where('key', 'shape_surcharge_percent')
            ->orderBy('valid_from')
            ->get();

        $this->assertCount(2, $versions);
        $this->assertSame('35', $versions[0]->value);
        $this->assertSame(
            Carbon::today()->subDay()->toDateString(),
            $versions[0]->valid_to?->toDateString(),
            'Poprzednia wersja musi zostać domknięta.',
        );
        $this->assertSame('40', $versions[1]->value);
        $this->assertNull($versions[1]->valid_to);
    }

    public function test_obowiazujaca_wartosc_to_ta_nowa(): void
    {
        $this->service->update(['shape_surcharge_percent' => '40']);

        $this->assertSame(40.0, GlobalParameter::number('shape_surcharge_percent'));
    }

    public function test_wczorajsza_wartosc_pozostaje_odczytywalna(): void
    {
        $this->service->update(['shape_surcharge_percent' => '40']);

        $this->assertSame(
            35.0,
            GlobalParameter::number('shape_surcharge_percent', Carbon::today()->subDay()),
            'Wycena sprzed zmiany musi dać się odtworzyć.',
        );
    }

    public function test_druga_zmiana_tego_samego_dnia_nie_mnozy_wersji(): void
    {
        $this->service->update(['shape_surcharge_percent' => '40']);
        $this->service->update(['shape_surcharge_percent' => '45']);

        $versions = GlobalParameter::query()->where('key', 'shape_surcharge_percent')->count();

        $this->assertSame(2, $versions, 'Poprawka tego samego dnia nie zakłada kolejnego wiersza.');
        $this->assertSame(45.0, GlobalParameter::number('shape_surcharge_percent'));
    }

    public function test_zapis_bez_zmian_nie_zostawia_sladu(): void
    {
        $errors = $this->service->update(['shape_surcharge_percent' => '35']);

        $this->assertSame([], $errors);
        $this->assertSame(1, GlobalParameter::query()->where('key', 'shape_surcharge_percent')->count());
        $this->assertSame(0, AuditEntry::query()->count());
    }

    public function test_jeden_zapis_to_jeden_wpis_w_dzienniku(): void
    {
        $this->service->update([
            'shape_surcharge_percent' => '40',
            'min_pane_price' => '70',
            'offer_validity_days' => '14',
        ]);

        $entries = AuditEntry::query()->get();

        $this->assertCount(1, $entries, 'Trzy zmiany z jednego kliknięcia to jeden wpis, nie trzy.');

        $changes = $entries->first()->changes ?? [];

        $this->assertCount(3, $changes);
        $this->assertSame('shape_surcharge_percent', $changes[0]['field']);
        $this->assertSame('35', $changes[0]['before']);
        $this->assertSame('40', $changes[0]['after']);
    }

    public function test_parametr_liczbowy_nie_przyjmuje_tekstu(): void
    {
        $errors = $this->service->update(['shape_surcharge_percent' => 'czterdziesci']);

        $this->assertArrayHasKey('shape_surcharge_percent', $errors);
        $this->assertSame(35.0, GlobalParameter::number('shape_surcharge_percent'));
    }

    public function test_odrzucenie_jednej_wartosci_wstrzymuje_caly_zapis(): void
    {
        $errors = $this->service->update([
            'min_pane_price' => '70',
            'shape_surcharge_percent' => 'czterdziesci',
        ]);

        $this->assertNotSame([], $errors);
        $this->assertSame(60.0, GlobalParameter::number('min_pane_price'), 'Zapis jest niepodzielny.');
    }

    /**
     * Ważność oferty istniała w starym systemie w dwóch miejscach
     * i rozjechała się: pole mówiło 10 dni, tekst dla klienta 7.
     * Szablon może odwoływać się wyłącznie do istniejącego parametru.
     */
    public function test_szablon_nie_moze_wskazywac_na_nieistniejacy_parametr(): void
    {
        $errors = $this->service->update([
            'offer_validity_text' => 'Ważność oferty: {{nie_ma_takiego}} dni',
        ]);

        $this->assertArrayHasKey('offer_validity_text', $errors);
    }

    public function test_szablon_z_istniejacym_parametrem_przechodzi(): void
    {
        $errors = $this->service->update([
            'offer_validity_text' => 'Oferta ważna {{offer_validity_days}} dni od wystawienia',
        ]);

        $this->assertSame([], $errors);
    }

    public function test_nieznany_klucz_jest_odrzucany(): void
    {
        $errors = $this->service->update(['nie_ma_takiego_parametru' => '1']);

        $this->assertArrayHasKey('nie_ma_takiego_parametru', $errors);
    }
}
