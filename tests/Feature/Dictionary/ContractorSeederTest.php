<?php

declare(strict_types=1);

namespace Tests\Feature\Dictionary;

use Tests\TestCase;
use App\Models\Contractor;
use App\Services\ContractorService;
use Database\Seeders\Core\RoleSeeder;
use PHPUnit\Framework\Attributes\Test;
use Database\Seeders\Dev\ContractorSeeder;
use Database\Seeders\Core\LocationSeeder;
use Database\Seeders\Core\GlassCatalogSeeder;
use Database\Seeders\Core\PriceSectionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Dane deweloperskie muszą przechodzić tę samą walidację co dane
 * wpisane ręcznie. Kartoteka z NIP-em z palca otwiera się na ekranie,
 * ale nie daje się zapisać z powrotem — i człowiek szuka błędu
 * w formularzu zamiast w seederze.
 */
class ContractorSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Contractor::query()->forceDelete();

        (new RoleSeeder())->run();
        (new LocationSeeder())->run();
        (new GlassCatalogSeeder())->run();
        (new PriceSectionSeeder())->run();
        (new ContractorSeeder())->run();
    }

    #[Test]
    public function seeder_zaklada_dwadziescia_kartotek(): void
    {
        $this->assertSame(20, Contractor::query()->count());
    }

    #[Test]
    public function ponowne_odpalenie_nie_mnozy_kartotek(): void
    {
        (new ContractorSeeder())->run();

        $this->assertSame(20, Contractor::query()->count());
    }

    #[Test]
    public function kazda_kartoteka_przechodzi_walidacje_zapisu(): void
    {
        $service = new ContractorService();

        foreach (Contractor::query()->with('addresses')->get() as $contractor) {
            $result = $service->save([
                'type' => $contractor->type->value,
                'name' => $contractor->name,
                'short_name' => $contractor->short_name,
                'tax_id' => $contractor->tax_id,
                'registry_id' => $contractor->registry_id,
                'first_name' => $contractor->first_name,
                'last_name' => $contractor->last_name,
                'phone' => $contractor->phone,
                'email' => $contractor->email,
                'payment_days' => $contractor->payment_days,
                'credit_limit' => $contractor->credit_limit,
                'is_supplier' => $contractor->is_supplier,
                'is_active' => $contractor->is_active,
            ], (int) $contractor->getKey());

            $this->assertSame(
                [],
                $result['errors'],
                sprintf('Kartoteka „%s" nie przechodzi walidacji.', $contractor->name),
            );
        }
    }

    #[Test]
    public function kartoteka_pokrywa_przypadki_brzegowe(): void
    {
        // Ekrany testuje się na danych, które mają skrajności: bez tego
        // wiersz wyłączony albo osoba bez NIP-u wychodzą dopiero
        // u klienta.
        $this->assertTrue(
            Contractor::query()->where('is_active', false)->exists(),
            'Brak kontrahenta wyłączonego.',
        );

        $this->assertTrue(
            Contractor::query()->where('type', 'person')->whereNull('tax_id')->exists(),
            'Brak osoby prywatnej bez NIP-u.',
        );

        $this->assertTrue(
            Contractor::query()->where('is_supplier', true)->exists(),
            'Brak dostawcy.',
        );

        $this->assertTrue(
            Contractor::query()->where('payment_days', 0)->where('credit_limit', 0)->exists(),
            'Brak kontrahenta na przedpłacie.',
        );

        $this->assertTrue(
            Contractor::query()->whereNull('phone')->whereNull('email')->exists(),
            'Brak kartoteki bez danych kontaktowych.',
        );
    }
}
