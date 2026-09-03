<?php

declare(strict_types=1);

namespace Tests\Feature\Users;

use Carbon\Carbon;
use Tests\TestCase;
use App\Models\User;
use App\Models\Location;
use Spatie\Permission\Models\Role;
use App\Services\UserBoardService;
use Database\Seeders\Core\RoleSeeder;
use PHPUnit\Framework\Attributes\Test;
use Database\Seeders\Core\LocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Ekran użytkowników dzieli konta na trzy grupy, bo znaczą co innego:
 * konto pracujące, konto założone i nigdy nieużyte, konto wyłączone.
 * W starym systemie leżały razem, więc nie dało się zobaczyć, że ktoś
 * ma dostęp, którego nigdy nie odebrał.
 */
class UserBoardTest extends TestCase
{
    use RefreshDatabase;

    private UserBoardService $service;

    protected function setUp(): void
    {
        parent::setUp();

        (new RoleSeeder())->run();
        (new LocationSeeder())->run();

        // Baza bywa zaseedowana kontem deweloperskim — test opisuje
        // wylacznie konta, ktore sam zaklada.
        User::query()->forceDelete();

        $this->service = new UserBoardService();
    }

    private function user(string $email, ?Carbon $lastLogin, bool $active = true): User
    {
        /** @var User $user */
        $user = User::query()->create([
            'first_name' => 'Jan',
            'last_name' => ucfirst(explode('@', $email)[0]),
            'email' => $email,
            'password' => 'x',
            'is_active' => $active,
            'last_login_at' => $lastLogin,
        ]);

        return $user;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function groups(Carbon $today): array
    {
        $groups = [];

        /** @var list<array{key: string, rows: list<array<string, mixed>>}> $raw */
        $raw = $this->service->board($today)['groups'];

        foreach ($raw as $group) {
            $groups[$group['key']] = $group;
        }

        return $groups;
    }

    #[Test]
    public function konto_bez_logowania_trafia_do_zaproszen(): void
    {
        $today = Carbon::parse('2026-09-03 12:00');

        $this->user('pracuje@test.pl', $today->copy()->subHours(2));
        $this->user('nigdy@test.pl', null);

        $groups = $this->groups($today);

        $this->assertCount(1, $groups['active']['rows']);
        $this->assertCount(1, $groups['invited']['rows']);
        $this->assertSame('nigdy@test.pl', $groups['invited']['rows'][0]['email']);
    }

    #[Test]
    public function wylaczone_konto_nie_liczy_sie_jako_zaproszenie(): void
    {
        $today = Carbon::parse('2026-09-03 12:00');

        // Wyłączone i nigdy nieużyte to nie zaproszenie czekające na
        // odbiór, tylko konto, które ktoś świadomie zamknął.
        $this->user('wylaczony@test.pl', null, active: false);

        $board = $this->service->board($today);
        $groups = $this->groups($today);

        $this->assertCount(0, $groups['invited']['rows']);
        $this->assertCount(1, $groups['disabled']['rows']);
        $this->assertSame(0, $board['summary']['invited']);
    }

    #[Test]
    public function podsumowanie_liczy_logowania_z_dzisiaj(): void
    {
        $today = Carbon::parse('2026-09-03 12:00');

        $this->user('dzis@test.pl', $today->copy()->subHours(3));
        $this->user('wczoraj@test.pl', $today->copy()->subDay());

        /** @var array<string, mixed> $summary */
        $summary = $this->service->board($today)['summary'];

        $this->assertSame(2, $summary['active']);
        $this->assertSame(1, $summary['logged_in_today']);
    }

    #[Test]
    public function konto_bez_logowania_od_progu_wymaga_uwagi(): void
    {
        $today = Carbon::parse('2026-09-03 12:00');

        $this->user('stary@test.pl', $today->copy()->subDays(UserBoardService::STALE_DAYS + 1));
        $this->user('swiezy@test.pl', $today->copy()->subDays(10));

        $board = $this->service->board($today);
        /** @var array<string, mixed> $summary */
        $summary = $board['summary'];

        $this->assertSame(1, $summary['stale']);
        $this->assertSame(UserBoardService::STALE_DAYS, $summary['stale_days']);

        $stale = null;

        foreach ($this->groups($today)['active']['rows'] as $row) {
            if ($row['email'] === 'stary@test.pl') {
                $stale = $row;
            }
        }

        $this->assertNotNull($stale);
        $this->assertTrue($stale['is_stale']);
    }

    #[Test]
    public function wiek_zaproszenia_liczy_sie_od_zalozenia_konta(): void
    {
        $today = Carbon::parse('2026-09-03 12:00');

        $user = $this->user('czeka@test.pl', null);
        $user->forceFill(['created_at' => $today->copy()->subDays(5)])->save();

        $board = $this->service->board($today);
        /** @var array<string, mixed> $summary */
        $summary = $board['summary'];

        $this->assertSame(5, $this->groups($today)['invited']['rows'][0]['waiting_days']);
        $this->assertSame(5, $summary['oldest_invite_days']);
    }

    #[Test]
    public function rola_nadrzedna_jest_widoczna_wprost(): void
    {
        $today = Carbon::parse('2026-09-03 12:00');

        $user = $this->user('admin@test.pl', $today->copy()->subHour());
        $user->roles()->attach(
            Role::query()->where('name', RoleSeeder::ADMINISTRATOR)->firstOrFail(),
        );

        $rows = $this->groups($today)['active']['rows'];

        $this->assertTrue($rows[0]['is_superuser']);
        $this->assertContains(RoleSeeder::ADMINISTRATOR, $rows[0]['roles']);
    }

    #[Test]
    public function wiersz_niesie_inicjaly_i_lokalizacje(): void
    {
        $today = Carbon::parse('2026-09-03 12:00');

        /** @var Location $location */
        $location = Location::query()->orderBy('position')->firstOrFail();

        $user = $this->user('anna@test.pl', $today->copy()->subHour());
        $user->forceFill([
            'first_name' => 'Anna',
            'last_name' => 'Bąk',
            'location_id' => $location->getKey(),
        ])->save();

        $rows = $this->groups($today)['active']['rows'];

        $this->assertSame('AB', $rows[0]['initials']);
        $this->assertSame($location->name, $rows[0]['location']);
    }
}
