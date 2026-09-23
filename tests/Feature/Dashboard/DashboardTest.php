<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use Tests\TestCase;
use App\Models\Role;
use App\Models\User;
use App\Models\Order;
use App\Models\Status;
use App\Models\Contractor;
use App\Enum\StatusDomain;
use App\Enum\ContractorType;
use App\Enum\DeliveryMethod;
use App\Services\DashboardService;
use Database\Seeders\Core\RoleSeeder;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Pulpit.
 *
 * Pilnowane są tu dwie rzeczy. Pierwsza jest **bezpieczeństwem**:
 * adres `/` jest otwarty dla każdego zalogowanego, więc przycinanie
 * sekcji musi robić serwer. Gdyby robił to front, magazynier dostałby
 * kwoty ofert w odpowiedzi API, nawet nie widząc ich na ekranie.
 *
 * Druga jest **spójnością**: pulpit nie liczy niczego sam, tylko
 * czyta z usług, z których czytają listy. Test na „moje" sprawdza
 * właśnie to — że wiersz pulpitu pochodzi z tej samej tablicy co
 * wiersz listy zleceń.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private DashboardService $board;

    protected function setUp(): void
    {
        parent::setUp();

        $this->board = new DashboardService();
    }

    #[Test]
    public function konto_bez_uprawnien_nie_dostaje_zadnej_sekcji(): void
    {
        $board = $this->board->board($this->user());

        // Nie pusta tablica, tylko `null` przy kazdej sekcji: ekran ma
        // powiedziec „nie masz dostepu", a nie „nic nie ma".
        $this->assertNull($board['mine']);
        $this->assertNull($board['orders']);
        $this->assertNull($board['production']);
        $this->assertNull($board['warehouse']);
        $this->assertNull($board['offers']);
    }

    #[Test]
    public function rola_nadrzedna_dostaje_wszystkie_sekcje(): void
    {
        /** @var Role $admin */
        $admin = Role::query()->where('name', RoleSeeder::ADMINISTRATOR)->firstOrFail();

        $user = $this->user();
        $user->assignRole($admin);

        $board = $this->board->board($user->fresh() ?? $user);

        $this->assertNotNull($board['orders']);
        $this->assertNotNull($board['production']);
        $this->assertNotNull($board['warehouse']);
        $this->assertNotNull($board['offers']);
    }

    #[Test]
    public function samo_uprawnienie_do_zasobu_nie_otwiera_sekcji(): void
    {
        // Dostep do modulu jest pietrem nad dostepem do zasobu (U-04),
        // wiec pulpit pyta o oba. Inaczej pokazywalby dane z modulu,
        // ktory listwa i tak chowa.
        $user = $this->userWith(['warehouse.list']);

        $this->assertNull($this->board->board($user)['warehouse']);
    }

    #[Test]
    public function dostep_do_modulu_i_zasobu_otwiera_sekcje(): void
    {
        $user = $this->userWith(['mag.access', 'warehouse.list']);

        $this->assertNotNull($this->board->board($user)['warehouse']);
    }

    #[Test]
    public function sekcja_osobista_bierze_zlecenia_zalozone_przez_uzytkownika(): void
    {
        $user = $this->userWith(['zlec.access', 'orders.list']);

        // Zlecenie po terminie: do sekcji osobistej trafia to,
        // z czym da sie cos zrobic albo co juz sie spoznia.
        $mine = $this->order(90001, (int) $user->getKey(), '2024-01-01');
        $this->order(90002, null, '2024-01-01');

        $board = $this->board->board($user);

        /** @var array<string, mixed> $section */
        $section = $board['mine'];
        $numbers = array_column($section['orders'], 'number');

        $this->assertSame([$mine->number], $numbers);
    }

    #[Test]
    public function zlecenia_widac_w_sekcji_ogolnej_niezaleznie_od_autora(): void
    {
        $user = $this->userWith(['zlec.access', 'orders.list']);

        $this->order(90003, null);

        /** @var array<string, mixed> $section */
        $section = $this->board->board($user)['orders'];

        // Pulpit nie liczy sam: pasma i „co dalej" pochodza z tej samej
        // uslugi, co lista zlecen.
        $this->assertGreaterThan(0, $section['ready_total'] + count($section['blocked']));
    }

    /** @param list<string> $permissions */
    private function userWith(array $permissions): User
    {
        /** @var Role $role */
        $role = Role::query()->create([
            'name' => 'Rola pulpitu ' . random_int(1000, 9999),
            'guard_name' => 'web',
        ]);

        $role->syncPermissions($permissions);

        $user = $this->user();
        $user->assignRole($role);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh() ?? $user;
    }

    private function user(): User
    {
        /** @var User */
        return User::query()->create([
            'first_name' => 'Test',
            'last_name' => 'Pulpit',
            'email' => 'pulpit' . random_int(1000, 9999) . '@example.test',
            'password' => 'secret-not-used',
            'is_active' => true,
        ]);
    }

    private function order(int $number, ?int $createdBy, ?string $deadline = null): Order
    {
        /** @var Status $status */
        $status = Status::findByCode(StatusDomain::ORDER, 'ZLECENIE');

        /** @var Contractor $party */
        $party = Contractor::query()->create([
            'type' => ContractorType::COMPANY->value,
            'name' => 'Klient ' . $number,
            'tax_id' => '8522347066',
        ]);

        /** @var Order */
        return Order::query()->create([
            'number' => $number,
            'contractor_id' => $party->id,
            'status_id' => $status->id,
            'delivery_method' => DeliveryMethod::PICKUP->value,
            'created_by' => $createdBy,
            'client_deadline' => $deadline,
        ]);
    }
}
