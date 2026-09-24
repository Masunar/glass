<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;
use App\Services\Orders\OrderValueStore;

/**
 * Przeliczenie zapamiętanych wartości zleceń.
 *
 * Na co dzień niepotrzebne — nieaktualna wartość przelicza się przy
 * pierwszym odczycie salda. Komenda jest na dwie sytuacje: po migracji,
 * żeby pierwszy człowiek na liście nie czekał za całą bazę, i po
 * zmianie w kodzie `OrderValue`, bo nowa reguła liczenia nie oznacza
 * sama żadnego zlecenia jako nieaktualnego (`--all`).
 */
class RefreshOrderValues extends Command
{
    protected $signature = 'glass:order-values
        {--all : Przelicz wszystkie, także oznaczone jako aktualne}';

    protected $description = 'Przelicza wartości zleceń zapamiętane na zleceniu.';

    public function handle(OrderValueStore $store): int
    {
        if ($this->option('all')) {
            Order::query()->update(['value_stale' => true]);
        }

        $count = $store->refreshStale();

        $this->info(sprintf('Przeliczone zlecenia: %d.', $count));

        return self::SUCCESS;
    }
}
