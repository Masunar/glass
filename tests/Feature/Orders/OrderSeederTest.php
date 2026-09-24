<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use Tests\TestCase;
use App\Models\Order;
use App\Models\ProductionTask;
use PHPUnit\Framework\Attributes\Test;
use Database\Seeders\Dev\OrderSeeder;
use Database\Seeders\Dev\FittingSeeder;
use Database\Seeders\Dev\GlassPriceSeeder;
use Database\Seeders\Dev\ContractorSeeder;
use Database\Seeders\Dev\ProcessPriceSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Dane próbne zleceń idą przez skutki swoich statusów.
 *
 * Zasiew wpisywał status wprost i omijał wszystko, co dzieje się przy
 * prawdziwym przejściu: trzy zlecenia stały „na produkcji", a hala nie
 * miała ani jednego zadania. Kolejka była pusta u wszystkich, więc
 * modułu produkcji nie dało się przejść żadną rolą — i nic tego nie
 * zgłaszało, bo pusta kolejka to też poprawny stan.
 */
class OrderSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Order::query()->delete();

        // Ta sama kolejnosc, co w DatabaseSeeder: zlecenia czytaja
        // cennik procesow, macierz szkla, okucia i kartoteke.
        (new ProcessPriceSeeder())->run();
        (new GlassPriceSeeder())->run();
        (new FittingSeeder())->run();
        (new ContractorSeeder())->run();
        (new OrderSeeder())->run();
    }

    #[Test]
    public function zlecenie_na_produkcji_ma_zadania_na_hali(): void
    {
        $orders = $this->inStatus('PRODUKCJA');

        $this->assertNotSame([], $orders, 'Dane próbne nie mają żadnego zlecenia na produkcji.');

        foreach ($orders as $order) {
            $this->assertTrue(
                ProductionTask::query()->where('order_id', $order->getKey())->exists(),
                sprintf('#%d stoi na produkcji bez jednego zadania na hali.', $order->number),
            );
        }
    }

    #[Test]
    public function zlecenie_gotowe_nie_udaje_wykonanej_pracy(): void
    {
        // Skutki tylko biezacego statusu, bez drogi do niego. Zadania
        // przy „Gotowe" musialyby byc od razu odhaczone przez kogos,
        // kogo nie ma — to byloby wymyslanie danych.
        foreach ($this->inStatus('GOTOWE') as $order) {
            $this->assertFalse(
                ProductionTask::query()->where('order_id', $order->getKey())->exists(),
                sprintf('#%d jest gotowe, a ma zadania na hali.', $order->number),
            );
        }
    }

    #[Test]
    public function zlecenie_przed_produkcja_nie_ma_zadan(): void
    {
        foreach (['ZLECENIE', 'DO_WYCENY'] as $code) {
            foreach ($this->inStatus($code) as $order) {
                $this->assertFalse(
                    ProductionTask::query()->where('order_id', $order->getKey())->exists(),
                    sprintf('#%d (%s) ma zadania, zanim weszło na produkcję.', $order->number, $code),
                );
            }
        }
    }

    /**
     * @return list<Order>
     */
    private function inStatus(string $code): array
    {
        /** @var list<Order> */
        return Order::query()
            ->whereHas('status', static fn(Builder $status): Builder => $status->where('code', $code))
            ->get()
            ->all();
    }
}
