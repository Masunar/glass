<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use Tests\TestCase;
use App\Models\Role;
use App\Models\User;
use App\Models\Order;
use App\Models\Status;
use App\Models\Location;
use App\Models\AuditEntry;
use App\Enum\StatusDomain;
use App\Models\Contractor;
use App\Enum\ContractorType;
use App\Enum\DeliveryMethod;
use App\Services\Orders\OrderService;
use App\Services\Orders\OrderOwnerService;
use App\Services\Orders\OrderBoardService;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Prowadzący zlecenie.
 *
 * Pilnowane są tu dwie rzeczy naraz, bo łatwo je pomylić.
 *
 * **Odpowiedzialność, nie dostęp.** Przypisanie osoby nie zabiera
 * widoku nikomu innemu: handlowiec musi odebrać telefon w sprawie
 * zlecenia kolegi. Test „lista bez filtra pokazuje cudze" istnieje
 * właśnie po to, żeby ta granica nie została przesunięta przypadkiem.
 *
 * **Jedna definicja słowa „moje".** Filtr na liście i kolejność na
 * pulpicie czytają to samo pole. Druga definicja — na przykład
 * „założone przeze mnie" — rozjechałaby się przy pierwszym przekazaniu
 * zlecenia i nie zgłosiłaby się w żaden sposób.
 */
class OrderOwnerTest extends TestCase
{
    use RefreshDatabase;

    private OrderOwnerService $owners;
    private OrderBoardService $board;

    protected function setUp(): void
    {
        parent::setUp();

        Order::query()->delete();

        $this->owners = new OrderOwnerService();
        $this->board = new OrderBoardService();
    }

    #[Test]
    public function zalozone_zlecenie_prowadzi_zakladajacy(): void
    {
        $user = $this->seller();
        $this->actingAs($user);

        $result = (new OrderService())->create([
            'contractor_id' => $this->contractor()->id,
            'delivery_method' => DeliveryMethod::PICKUP->value,
            'pickup_location_id' => $this->pickupPoint()->id,
        ]);

        $this->assertSame([], $result['errors']);

        /** @var Order $order */
        $order = Order::query()->findOrFail($result['id']);

        // Jedyne uczciwe domyslne: dopoki nikt nie przekazal zlecenia
        // dalej, odpowiada ten, kto je zalozyl. Puste pole znaczyloby
        // „niczyje" przy kazdym nowym zleceniu.
        $this->assertSame((int) $user->getKey(), $order->owner_id);
        $this->assertSame((int) $user->getKey(), $order->created_by);
    }

    #[Test]
    public function przekazanie_zmienia_prowadzacego_i_zostawia_slad(): void
    {
        $from = $this->seller('Anna', 'Kowalska');
        $to = $this->seller('Piotr', 'Nowak');
        $order = $this->order(91001, $from);

        $result = $this->owners->change((int) $order->getKey(), (int) $to->getKey());

        $this->assertSame([], $result['errors']);
        $this->assertSame((int) $to->getKey(), (int) ($order->fresh()?->owner_id));

        // Przekazanie jest decyzja, nie poprawka literowki — po tygodniu
        // ktos zapyta, od kiedy to jego sprawa.
        $entry = AuditEntry::query()
            ->where('auditable_type', Order::class)
            ->where('auditable_id', $order->getKey())
            ->where('event', 'owner_changed')
            ->first();

        $this->assertNotNull($entry);
    }

    #[Test]
    public function zakladajacy_zostaje_ten_sam_po_przekazaniu(): void
    {
        $from = $this->seller();
        $to = $this->seller();
        $order = $this->order(91002, $from);

        $this->owners->change((int) $order->getKey(), (int) $to->getKey());

        // Dwa pola, dwie odpowiedzi: „kto to wpisal" i „kogo pytac
        // dzisiaj". Jedno pole w obu rolach falszowaloby historie przy
        // kazdej zmianie opiekuna.
        $this->assertSame((int) $from->getKey(), (int) ($order->fresh()?->created_by));
    }

    #[Test]
    public function prowadzacym_nie_zostanie_ktos_bez_dostepu_do_zlecen(): void
    {
        $from = $this->seller();
        $order = $this->order(91003, $from);

        $warehouse = $this->userWith(['mag.access', 'warehouse.list']);

        $result = $this->owners->change((int) $order->getKey(), (int) $warehouse->getKey());

        // Wskazanie kogos, kto zlecen nie widzi, nie jest przekazaniem,
        // tylko cichym zgubieniem sprawy: nie zobaczy jej ani na
        // liscie, ani na pulpicie.
        $this->assertArrayHasKey('owner_id', $result['errors']);
        $this->assertSame((int) $from->getKey(), (int) ($order->fresh()?->owner_id));
    }

    #[Test]
    public function kandydaci_to_konta_widzace_zlecenia(): void
    {
        $seller = $this->seller();
        $warehouse = $this->userWith(['mag.access', 'warehouse.list']);

        $ids = array_column($this->owners->candidates(), 'id');

        $this->assertContains((int) $seller->getKey(), $ids);
        $this->assertNotContains((int) $warehouse->getKey(), $ids);
    }

    #[Test]
    public function wylaczone_konto_nie_prowadzi_zlecen(): void
    {
        $from = $this->seller();
        $order = $this->order(91004, $from);

        $gone = $this->seller();
        $gone->is_active = false;
        $gone->save();

        $result = $this->owners->change((int) $order->getKey(), (int) $gone->getKey());

        $this->assertArrayHasKey('owner_id', $result['errors']);
        $this->assertNotContains(
            (int) $gone->getKey(),
            array_column($this->owners->candidates(), 'id'),
        );
    }

    #[Test]
    public function filtr_moje_zaweza_liste_razem_z_licznikami(): void
    {
        $me = $this->seller();
        $other = $this->seller();

        $this->order(91005, $me);
        $this->order(91006, $other);
        $this->order(91007, $other);

        $board = $this->board->board(ownerId: (int) $me->getKey());

        $this->assertSame(1, $board['summary']['shown']);
        $this->assertTrue($board['summary']['mine']);

        // Zakladka „Wszystkie" nad wlasna lista musi mowic o tej samej
        // liscie. Licznik liczony z calosci nie jest pomylka na ekranie,
        // tylko druga definicja zbioru — i nie zglasza sie sam.
        $this->assertSame(1, $board['filters'][0]['count']);
    }

    #[Test]
    public function bez_filtra_widac_zlecenia_wszystkich(): void
    {
        $me = $this->seller();
        $other = $this->seller();

        $this->order(91008, $me);
        $this->order(91009, $other);

        // Prowadzacy mowi, kto odpowiada, a nie kto moze ogladac.
        // Handlowiec musi odebrac telefon w sprawie zlecenia kolegi.
        $this->assertSame(2, $this->board->board()['summary']['shown']);
        $this->assertFalse($this->board->board()['summary']['mine']);
    }

    #[Test]
    public function wiersz_podpisuje_prowadzacy_a_nie_zakladajacy(): void
    {
        $author = $this->seller('Anna', 'Kowalska');
        $owner = $this->seller('Piotr', 'Nowak');

        $order = $this->order(91010, $author);
        $this->owners->change((int) $order->getKey(), (int) $owner->getKey());

        $rows = [];

        /** @var list<array{rows: list<array<string, mixed>>}> $bands */
        $bands = $this->board->board()['bands'];

        foreach ($bands as $band) {
            foreach ($band['rows'] as $row) {
                $rows[] = $row;
            }
        }

        $this->assertCount(1, $rows);
        $this->assertSame('PN', $rows[0]['owner_initials']);
        $this->assertSame((int) $owner->getKey(), $rows[0]['owner_id']);
    }

    private function contractor(): Contractor
    {
        /** @var Contractor */
        return Contractor::query()->create([
            'type' => ContractorType::COMPANY->value,
            'name' => 'Prowadzacy ' . random_int(1000, 9999),
            'tax_id' => '8522347066',
        ]);
    }

    private function pickupPoint(): Location
    {
        /** @var Location */
        return Location::query()->where('is_pickup_point', true)->firstOrFail();
    }

    private function order(int $number, User $owner): Order
    {
        /** @var Status $status */
        $status = Status::findByCode(StatusDomain::ORDER, 'ZLECENIE');

        /** @var Order */
        return Order::query()->create([
            'number' => $number,
            'contractor_id' => $this->contractor()->id,
            'status_id' => $status->id,
            'delivery_method' => DeliveryMethod::PICKUP->value,
            'created_by' => $owner->getKey(),
            'owner_id' => $owner->getKey(),
        ]);
    }

    private function seller(string $first = 'Test', string $last = 'Handlowiec'): User
    {
        return $this->userWith(['zlec.access', 'orders.list', 'orders.update'], $first, $last);
    }

    /**
     * @param list<string> $permissions
     */
    private function userWith(
        array $permissions,
        string $first = 'Test',
        string $last = 'Konto',
    ): User {
        /** @var Role $role */
        $role = Role::query()->create([
            'name' => 'Rola prowadzacego ' . random_int(1000, 9999),
            'guard_name' => 'web',
        ]);

        $role->syncPermissions($permissions);

        /** @var User $user */
        $user = User::query()->create([
            'first_name' => $first,
            'last_name' => $last,
            'email' => 'prowadzacy' . random_int(1000, 99999) . '@example.test',
            'password' => 'secret-not-used',
            'is_active' => true,
        ]);

        $user->assignRole($role);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }
}
