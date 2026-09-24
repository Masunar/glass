<?php

declare(strict_types=1);

namespace Tests\Feature\Simulation;

use Tests\TestCase;
use App\Models\User;
use App\Models\Order;
use App\Models\OrderList;
use App\Models\OrderItem;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\Storage;
use Database\Seeders\Dev\GlassPriceSeeder;
use Database\Seeders\Dev\ContractorSeeder;
use Database\Seeders\Dev\ProcessPriceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Symulacja dużej bazy — na trzech zleceniach, nie na dziesięciu
 * tysiącach.
 *
 * Test nie mierzy czasu, bo ten zależy od maszyny. Pilnuje, że komenda
 * przechodzi przez usługi aplikacji bez błędu także na SQLite, że
 * zlecenia mają zamówiony kształt i że raport ląduje w pliku —
 * inaczej pierwsze uruchomienie na dziesięciu tysiącach wywaliłoby się
 * po kwadransie na czymś, co dało się złapać w sekundę.
 */
class SimulateOrdersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Order::query()->delete();
        Storage::fake('local');

        (new ProcessPriceSeeder())->run();
        (new GlassPriceSeeder())->run();
        (new ContractorSeeder())->run();

        User::query()->create([
            'first_name' => 'Test',
            'last_name' => 'Symulacja',
            'email' => 'symulacja@example.test',
            'password' => 'secret-not-used',
            'is_active' => true,
        ]);
    }

    #[Test]
    public function zlecenia_maja_zamowiony_ksztalt(): void
    {
        $this->artisan('glass:simulate', ['--orders' => 5, '--seed' => 7])->assertExitCode(0);

        $this->assertSame(5, Order::query()->count());

        foreach (Order::query()->pluck('id') as $orderId) {
            $lists = OrderList::query()->where('order_id', $orderId)->pluck('id');

            $this->assertGreaterThanOrEqual(1, $lists->count());
            $this->assertLessThanOrEqual(3, $lists->count());

            foreach ($lists as $listId) {
                $items = OrderItem::query()->where('order_list_id', $listId)->count();

                // Formatka odrzucona przez walidacje nie powstaje, wiec
                // gorna granica jest twarda, a dolna mowi, ze droga
                // przez ekran w ogole dziala.
                $this->assertGreaterThanOrEqual(1, $items);
                $this->assertLessThanOrEqual(10, $items);
            }
        }
    }

    #[Test]
    public function zlecenie_ma_prowadzacego_z_bazy(): void
    {
        $this->artisan('glass:simulate', ['--orders' => 2, '--seed' => 7])->assertExitCode(0);

        // Zakladanie idzie jako konto z bazy — dokladnie jak z ekranu.
        // Zlecenie bez prowadzacego byloby czyms, czego aplikacja nie
        // tworzy.
        $this->assertSame(0, Order::query()->whereNull('owner_id')->count());
    }

    #[Test]
    public function raport_laduje_w_pliku(): void
    {
        $this->artisan('glass:simulate', ['--orders' => 1, '--seed' => 7])->assertExitCode(0);

        $files = Storage::disk('local')->files('symulacja');

        $this->assertCount(1, $files);
        $this->assertStringContainsString('## Rozjazdy', (string) Storage::disk('local')->get($files[0]));
    }

    #[Test]
    public function sam_pomiar_niczego_nie_zaklada(): void
    {
        $this->artisan('glass:simulate', ['--measure-only' => true])->assertExitCode(0);

        $this->assertSame(0, Order::query()->count());
    }
}
