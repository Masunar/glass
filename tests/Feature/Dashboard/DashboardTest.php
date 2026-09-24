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
    public function konto_bez_uprawnien_nie_dostaje_zadnej_liczby(): void
    {
        $board = $this->board->board($this->user());

        // `null`, nie zero: kreska na ekranie znaczy „nie masz do tego
        // dostepu", a zero — „nie ma sie czym zajmowac".
        foreach ($board['counters'] as $key => $value) {
            $this->assertNull($value, sprintf('Licznik "%s" wyciekl.', $key));
        }

        $this->assertSame([], $board['tasks']);
        $this->assertSame([], $board['blocked']);
        $this->assertNull($board['shortages']);
        // Alert dotyczy zlecenia, wiec pasmo alertow przycina to samo
        // uprawnienie, co lista spraw. Alert nie ma wlasnego.
        $this->assertSame([], $board['alerts']);
    }

    #[Test]
    public function pasmo_alertow_przychodzi_z_dostepem_do_zlecen(): void
    {
        $user = $this->userWith(['zlec.access', 'orders.list']);

        $late = $this->order(90007, null, '2024-01-01');

        $alerts = $this->board->board($user)['alerts'];

        // Zaseedowana regula „po terminie" ma sie zapalic sama, bez
        // niczyjego zapisu — na tym polega przebieg na odczycie.
        $this->assertNotSame([], $alerts);
        $this->assertSame('order_overdue', $alerts[0]['code']);
        $this->assertSame(1, $alerts[0]['count']);
        // Liczba bez miejsca, w ktore mozna z nia pojsc, kaze szukac
        // recznie — stad podpisy rzeczy przy regule. Od #38 nie sa to
        // juz „zlecenia": regula moze dotyczyc produktu albo partii.
        $this->assertSame('#90007', $alerts[0]['subjects'][0]['label']);
        $this->assertSame('/orders/' . $late->getKey(), $alerts[0]['subjects'][0]['path']);
    }

    #[Test]
    public function rola_nadrzedna_dostaje_wszystkie_liczby(): void
    {
        /** @var Role $admin */
        $admin = Role::query()->where('name', RoleSeeder::ADMINISTRATOR)->firstOrFail();

        $user = $this->user();
        $user->assignRole($admin);

        $counters = $this->board->board($user->fresh() ?? $user)['counters'];

        foreach ($counters as $key => $value) {
            $this->assertNotNull($value, sprintf('Licznik "%s" nie doszedl.', $key));
        }
    }

    #[Test]
    public function samo_uprawnienie_do_zasobu_nie_otwiera_licznika(): void
    {
        // Dostep do modulu jest pietrem nad dostepem do zasobu (U-04),
        // wiec pulpit pyta o oba. Inaczej pokazywalby dane z modulu,
        // ktory listwa i tak chowa.
        $user = $this->userWith(['warehouse.list']);

        $this->assertNull($this->board->board($user)['counters']['shortages']);
    }

    #[Test]
    public function dostep_do_modulu_i_zasobu_otwiera_licznik(): void
    {
        $user = $this->userWith(['mag.access', 'warehouse.list']);

        $this->assertNotNull($this->board->board($user)['counters']['shortages']);
        $this->assertNotNull($this->board->board($user)['shortages']);
    }

    #[Test]
    public function zlecenie_po_terminie_jest_sprawa(): void
    {
        $user = $this->userWith(['zlec.access', 'orders.list']);

        $late = $this->order(90001, null, '2024-01-01');

        $board = $this->board->board($user);

        $this->assertSame([$late->number], array_column($board['tasks'], 'number'));
        $this->assertSame('overdue', $board['tasks'][0]['band']);
        // Termin slowem, nie surowa data — i liczy go serwer, zeby
        // pulpit i lista nie nazwaly tego samego dnia inaczej.
        $this->assertStringEndsWith('dni po', (string) $board['tasks'][0]['deadline_label']);
    }

    #[Test]
    public function pierwsza_sprawa_jest_propozycja_startu(): void
    {
        $user = $this->userWith(['zlec.access', 'orders.list']);

        $this->order(90004, null, '2024-01-01');

        $board = $this->board->board($user);

        // Ekran ma dac jeden punkt wejscia, nie siedem rownorzednych.
        $this->assertNotNull($board['top']);
        $this->assertSame($board['tasks'][0]['number'], $board['top']['number']);
    }

    #[Test]
    public function zamkniete_zlecenie_po_terminie_nie_jest_sprawa(): void
    {
        $user = $this->userWith(['zlec.access', 'orders.list']);

        /** @var Status $final */
        $final = Status::query()
            ->where('domain', StatusDomain::ORDER->value)
            ->where('is_final', true)
            ->firstOrFail();

        $closed = $this->order(90008, null, '2024-01-01');
        $closed->status_id = $final->id;
        $closed->save();

        $board = $this->board->board($user);

        // Pasmo liczy lista i robi to swiadomie: zlecenie w statusie
        // koncowym nie ma terminu do pilnowania. Pulpit liczyl je drugi
        // raz z samego `days_left`, wiec rozliczone zlecenie sprzed
        // miesiaca wracalo jako sprawa na dzis — a kafelek i zakladka
        // pokazywaly dwie rozne liczby tego samego.
        $this->assertNotContains($closed->number, array_column($board['tasks'], 'number'));
        $this->assertSame($board['counters']['overdue'], $board['summary']['overdue']);
    }

    #[Test]
    public function zablokowane_nie_sa_sprawami_i_licza_sie_po_powodzie(): void
    {
        $user = $this->userWith(['zlec.access', 'orders.list']);

        $this->order(90005, null);

        $board = $this->board->board($user);

        // Zlecenie bez dostepnego ruchu i bez terminu nie jest sprawa:
        // `firstBlocked()` zwraca cos przy prawie kazdym, wiec lista
        // zamienilaby sie w kopie listy zlecen.
        $this->assertSame([], array_column($board['tasks'], 'number'));
        $this->assertNotSame([], $board['blocked']);
        $this->assertArrayHasKey('reason', $board['blocked'][0]);
        $this->assertArrayHasKey('count', $board['blocked'][0]);
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
