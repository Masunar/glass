<?php

declare(strict_types=1);

namespace Tests\Feature\Alerts;

use Carbon\Carbon;
use Tests\TestCase;
use App\Enum\Unit;
use App\Models\Role;
use App\Models\User;
use App\Models\Order;
use App\Enum\Section;
use App\Models\Status;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Contractor;
use App\Models\ProductGroup;
use App\Enum\StatusDomain;
use App\Enum\ContractorType;
use App\Enum\DeliveryMethod;
use App\Services\Alerts\AlertBoard;
use App\Services\Alerts\AlertEngine;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Adresowanie alertów do prowadzącego.
 *
 * Alert nie ma zapisanego odbiorcy — **odbiorcę wyprowadza się z rzeczy,
 * której alert dotyczy**. Zapisany rozjechałby się przy pierwszym
 * przekazaniu zlecenia i wisiał przy poprzedniej osobie bez żadnego
 * objawu, więc testy pilnują wyprowadzenia, a nie samego sortowania.
 *
 * Druga rzecz pilnowana tutaj: **nic nie znika**. Cudza sprawa po
 * terminie zostaje w paśmie — ktoś musi ją zobaczyć, gdy prowadzący ma
 * urlop — a rzecz bez właściciela nie udaje niczyjej.
 */
class AlertOwnerTest extends TestCase
{
    use RefreshDatabase;

    private AlertBoard $board;
    private Carbon $day;

    protected function setUp(): void
    {
        parent::setUp();

        Order::query()->delete();

        $this->board = new AlertBoard(new AlertEngine());
        $this->day = Carbon::parse('2026-03-10');
    }

    #[Test]
    public function moj_podpis_idzie_przed_cudzym(): void
    {
        $me = $this->user('Marcin', 'Szewczyk');
        $other = $this->user('Piotr', 'Nowak');

        // Cudze zlecenie jest pierwsze w przebiegu — po numerze i po
        // kolejnosci zakladania.
        $this->order(70101, $other, '2026-03-01');
        $mine = $this->order(70102, $me, '2026-03-01');

        $subjects = $this->board->subjectsFor(
            'order_overdue',
            5,
            $this->day,
            (int) $me->getKey(),
        );

        $this->assertSame('#' . $mine->number, $subjects[0]['label']);
        $this->assertTrue($subjects[0]['is_mine']);

        // Cudza sprawa zostaje — zlecenie po terminie jest po terminie
        // niezaleznie od tego, czyje jest.
        $this->assertCount(2, $subjects);
        $this->assertFalse($subjects[1]['is_mine']);
    }

    #[Test]
    public function cudza_sprawa_niesie_prowadzacego(): void
    {
        $me = $this->user('Marcin', 'Szewczyk');
        $other = $this->user('Piotr', 'Nowak');

        $this->order(70103, $other, '2026-03-01');

        $subjects = $this->board->subjectsFor(
            'order_overdue',
            5,
            $this->day,
            (int) $me->getKey(),
        );

        // Inicjaly do podpisu, pelne imie do podpowiedzi pod kursorem:
        // dwie osoby moga miec te same inicjaly i wtedy sam podpis nie
        // rozstrzyga niczego.
        $this->assertSame('PN', $subjects[0]['owner_initials']);
        $this->assertSame('Piotr Nowak', $subjects[0]['owner']);
    }

    #[Test]
    public function kolejnosc_ustala_sie_przed_przycieciem(): void
    {
        $me = $this->user('Marcin', 'Szewczyk');
        $other = $this->user('Piotr', 'Nowak');

        foreach ([70104, 70105, 70106, 70107, 70108] as $number) {
            $this->order($number, $other, '2026-03-01');
        }

        $mine = $this->order(70109, $me, '2026-03-01');

        $subjects = $this->board->subjectsFor(
            'order_overdue',
            5,
            $this->day,
            (int) $me->getKey(),
        );

        // Szesc spraw, piec miejsc. Gdyby przyciecie szlo przed
        // sortowaniem, moje zlecenie znikneloby, zanim cokolwiek
        // zdazyloby je przesunac — i nikt by sie o tym nie dowiedzial.
        $this->assertCount(5, $subjects);
        $this->assertSame('#' . $mine->number, $subjects[0]['label']);
    }

    #[Test]
    public function regula_z_moja_sprawa_idzie_przed_pozostalymi(): void
    {
        $me = $this->user('Marcin', 'Szewczyk');
        $other = $this->user('Piotr', 'Nowak');

        // Cudze zlecenie po terminie: regula stoi w panelu wyzej.
        $this->order(70110, $other, '2026-03-01');

        // Moje zlecenie wstrzymane: regula stoi w panelu nizej i bez
        // adresowania nigdy nie wyprzedziloby tamtej.
        $mine = $this->order(70111, $me, null);
        $mine->is_on_hold = true;
        $mine->hold_reason = 'Klient zawiesił';
        $mine->save();

        $codes = array_column($this->board->summary($this->day, (int) $me->getKey()), 'code');

        // Zadna regula nie znika — zmienia sie wylacznie kolejnosc.
        $this->assertContains('order_overdue', $codes);
        $this->assertContains('order_on_hold', $codes);

        $this->assertLessThan(
            array_search('order_overdue', $codes, true),
            array_search('order_on_hold', $codes, true),
        );
    }

    #[Test]
    public function bez_wskazanej_osoby_kolejnosc_zostaje_panelowa(): void
    {
        $me = $this->user('Marcin', 'Szewczyk');
        $other = $this->user('Piotr', 'Nowak');

        $first = $this->order(70112, $other, '2026-03-01');
        $this->order(70113, $me, '2026-03-01');

        // Bez uzytkownika nie ma czego adresowac — kolejnosc zostaje ta
        // z przebiegu. To takze zabezpieczenie przed cicha zmiana:
        // gdyby pasmo zaczelo przestawiac wiersze samo, ten test pekłby.
        $subjects = $this->board->subjectsFor('order_overdue', 5, $this->day);

        $this->assertSame('#' . $first->number, $subjects[0]['label']);
        $this->assertFalse($subjects[0]['is_mine']);
    }

    #[Test]
    public function rzecz_bez_wlasciciela_nie_dostaje_podpisu(): void
    {
        $me = $this->user('Marcin', 'Szewczyk');

        /** @var ProductGroup $group */
        $group = ProductGroup::query()->firstOrCreate(
            ['name' => 'Alerty prowadzącego'],
            ['section' => Section::FITTINGS->value, 'position' => 910, 'is_active' => true],
        );

        /** @var Product $product */
        $product = Product::query()->create([
            'product_group_id' => $group->getKey(),
            'section' => Section::FITTINGS->value,
            'code' => 'OWN' . random_int(1000, 9999),
            'name' => 'Zawias bez opiekuna',
            'unit' => Unit::PIECE->value,
            'is_active' => true,
        ]);

        StockLevel::query()->create([
            'product_id' => $product->getKey(),
            'quantity' => 2,
            'min_quantity' => 5,
            'max_quantity' => 12,
        ]);

        $subjects = $this->board->subjectsFor(
            'stock_below_minimum',
            5,
            $this->day,
            (int) $me->getKey(),
        );

        // Produkt na magazynie nie nalezy do nikogo. Podpisanie go
        // kimkolwiek byloby wymyslaniem danych, a „moje" na cudzej
        // rzeczy — falszem.
        $this->assertNotSame([], $subjects);
        $this->assertNull($subjects[0]['owner']);
        $this->assertNull($subjects[0]['owner_initials']);
        $this->assertFalse($subjects[0]['is_mine']);
    }

    private function order(int $number, User $owner, ?string $deadline): Order
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
            'client_deadline' => $deadline,
            'created_by' => $owner->getKey(),
            'owner_id' => $owner->getKey(),
            // Komplet rysunkow, zeby regula „brak rysunkow" nie mieszala
            // sie do testow o kolejnosci.
            'drawings_complete_at' => Carbon::parse('2026-02-01'),
        ]);
    }

    private function user(string $first, string $last): User
    {
        /** @var Role $role */
        $role = Role::query()->create([
            'name' => 'Rola prowadzącego alertu ' . random_int(1000, 9999),
            'guard_name' => 'web',
        ]);

        $role->syncPermissions(['zlec.access', 'orders.list']);

        /** @var User $user */
        $user = User::query()->create([
            'first_name' => $first,
            'last_name' => $last,
            'email' => 'alert' . random_int(1000, 99999) . '@example.test',
            'password' => 'secret-not-used',
            'is_active' => true,
        ]);

        $user->assignRole($role);

        return $user;
    }
}
