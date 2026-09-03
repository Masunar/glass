<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Contractor;
use App\Models\AuditEntry;
use App\Services\AuditTrail;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Dziennik zmian stał wcześniej w trzech serwisach w trzech kopiach.
 * Teraz jest jeden i ma własny test — bo od tej pory każda zmiana
 * w nim dotyczy naraz kartoteki, słowników i cennika.
 */
class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    private AuditTrail $audit;

    protected function setUp(): void
    {
        parent::setUp();

        AuditEntry::query()->delete();

        $this->audit = new AuditTrail();
    }

    private function entry(): AuditEntry
    {
        /** @var AuditEntry */
        return AuditEntry::query()->firstOrFail();
    }

    #[Test]
    public function nowy_rekord_to_zdarzenie_created(): void
    {
        $this->audit->record(Contractor::class, 7, null, ['name' => 'Stecko', 'is_active' => true]);

        $entry = $this->entry();

        $this->assertSame('created', $entry->event);
        $this->assertSame(Contractor::class, $entry->auditable_type);
        $this->assertSame(7, $entry->auditable_id);
        $this->assertCount(2, (array) $entry->changes);
    }

    #[Test]
    public function do_dziennika_trafiaja_tylko_pola_zmienione(): void
    {
        // Bez tego kazdy zapis formularza produkowalby sciane wierszy
        // bez tresci, a dziennik przestalby byc czytelny.
        $this->audit->record(
            Contractor::class,
            7,
            ['name' => 'Stecko', 'payment_days' => 14],
            ['name' => 'Stecko Meble', 'payment_days' => 14],
        );

        /** @var list<array{field: string, before: mixed, after: mixed}> $changes */
        $changes = (array) $this->entry()->changes;

        $this->assertCount(1, $changes);
        $this->assertSame('name', $changes[0]['field']);
        $this->assertSame('Stecko', $changes[0]['before']);
        $this->assertSame('Stecko Meble', $changes[0]['after']);
    }

    #[Test]
    public function zapis_bez_zmian_nie_zostawia_sladu(): void
    {
        $this->audit->record(Contractor::class, 7, ['name' => 'Stecko'], ['name' => 'Stecko']);

        $this->assertSame(0, AuditEntry::query()->count());
    }

    #[Test]
    public function pusta_lista_zmian_nie_zostawia_sladu(): void
    {
        $this->audit->write(Contractor::class, 7, []);

        $this->assertSame(0, AuditEntry::query()->count());
    }

    #[Test]
    public function jeden_zapis_to_jeden_wpis_i_jedna_sesja(): void
    {
        $this->audit->record(Contractor::class, 7, null, [
            'name' => 'Stecko',
            'phone' => '914 843 703',
            'payment_days' => 14,
        ]);

        $entries = AuditEntry::query()->get();

        $this->assertCount(1, $entries, 'Trzy pola z jednego zapisu to jeden wpis, nie trzy.');
        $this->assertNotNull($this->entry()->edit_session_id);
    }

    #[Test]
    public function wpis_zapamietuje_autora(): void
    {
        /** @var User $user */
        $user = User::query()->create([
            'first_name' => 'Marcin',
            'email' => 'audit@test.pl',
            'password' => 'x',
            'is_active' => true,
        ]);

        $this->actingAs($user);

        $this->audit->record(Contractor::class, 7, null, ['name' => 'Stecko']);

        $this->assertSame((int) $user->getKey(), $this->entry()->user_id);
    }
}
