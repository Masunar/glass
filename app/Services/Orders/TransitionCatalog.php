<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Models\StatusTransition;
use Illuminate\Database\Eloquent\Collection;

/**
 * Przejścia wychodzące ze statusu, spamiętane na czas żądania.
 *
 * Katalog przejść to konfiguracja, a lista zleceń pytała o nią dla
 * każdego wiersza — i to dwa razy, bo po „pierwszym dostępnym" szuka
 * jeszcze „pierwszego zablokowanego". Symulacja na 10 000 zleceń:
 * trzy zapytania na wiersz, sześćset na jedno otwarcie listy, a statusów
 * jest kilkanaście.
 */
final class TransitionCatalog
{
    /** @var array<int, Collection<int, StatusTransition>> */
    private array $memo = [];

    /**
     * @return Collection<int, StatusTransition>
     */
    public function from(int $statusId): Collection
    {
        return $this->memo[$statusId] ??= StatusTransition::query()
            ->with('toStatus')
            ->where('from_status_id', $statusId)
            ->where('is_active', true)
            ->orderBy('position')
            ->get();
    }
}
