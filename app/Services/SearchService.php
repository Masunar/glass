<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Order;
use App\Models\Product;
use App\Models\Location;
use App\Enum\Permission;
use App\Models\Contractor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;

/**
 * Wyszukiwanie ogólne — jedno pole na całą aplikację.
 *
 * Stary system miał osobne wyszukiwanie na każdym ekranie, więc szukając
 * numeru trzeba było najpierw wiedzieć, gdzie on mieszka. Tutaj pytanie
 * idzie do wszystkiego naraz, a odpowiedź niesie moduł, z którego
 * pochodzi — kolor grupy to ten sam kolor, co na listwie.
 *
 * Zapytanie nigdy nie zwraca pozycji, do której pytający nie ma
 * uprawnienia: filtr jest po stronie serwera, nie po stronie widoku.
 */
final readonly class SearchService
{
    /** Poniżej dwóch znaków każde zapytanie pasuje do wszystkiego. */
    public const MIN_LENGTH = 2;

    /** Ile trafień na grupę. Lista ma prowadzić do ekranu, nie zastępować go. */
    private const PER_GROUP = 6;

    /**
     * @return list<array<string, mixed>>
     */
    public function search(string $query): array
    {
        $needle = trim($query);

        if (mb_strlen($needle) < self::MIN_LENGTH) {
            return [];
        }

        $groups = [
            $this->orders($needle),
            $this->contractors($needle),
            $this->products($needle),
            $this->users($needle),
            $this->locations($needle),
        ];

        return array_values(array_filter(
            $groups,
            static fn(?array $group): bool => $group !== null && $group['hits'] !== [],
        ));
    }

    /**
     * Numer zlecenia to pierwsze, czego szuka biuro — i jedyne, co klient
     * podaje przez telefon. Dlatego ta grupa stoi na górze.
     *
     * Jako jedyna nie sprawdza uprawnienia, bo uprawnienia do zleceń
     * jeszcze nie ma — moduł nie ma ekranów. Kiedy powstanie, ta metoda
     * musi dostać taki sam filtr jak pozostałe.
     *
     * @return array<string, mixed>
     */
    private function orders(string $needle): array
    {
        $digits = preg_replace('/\D+/', '', $needle) ?? '';

        $orders = Order::query()
            ->with(['contractor', 'status'])
            ->when(
                $digits !== '',
                static fn(Builder $query): Builder => $query->where('number', 'like', $digits . '%'),
                static fn(Builder $query): Builder => $query->whereRaw('1 = 0'),
            )
            ->orderByDesc('number')
            ->limit(self::PER_GROUP)
            ->get();

        $hits = [];

        foreach ($orders as $order) {
            $hits[] = [
                'id' => (int) $order->getKey(),
                'title' => '#' . $order->number,
                'subtitle' => trim(implode(' · ', array_filter([
                    $order->contractor?->displayName(),
                    $order->status?->name,
                ]))),
                'path' => '/orders/' . $order->getKey(),
            ];
        }

        return $this->group('orders', 'Zlecenia', 'zlec', $hits);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function contractors(string $needle): ?array
    {
        if (!$this->allowed(Permission::CONTRACTORS)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $needle) ?? '';

        $contractors = Contractor::query()
            ->where(static function (Builder $query) use ($needle, $digits): void {
                $query
                    ->where('name', 'like', '%' . $needle . '%')
                    ->orWhere('short_name', 'like', '%' . $needle . '%')
                    ->orWhere('email', 'like', '%' . $needle . '%');

                // NIP i telefon wpisuje sie ze spacjami albo bez, wiec
                // szukamy po samych cyfrach.
                if (mb_strlen($digits) >= 3) {
                    $query
                        ->orWhere('tax_id', 'like', $digits . '%')
                        ->orWhere('phone', 'like', '%' . $digits . '%');
                }
            })
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->limit(self::PER_GROUP)
            ->get();

        $hits = [];

        foreach ($contractors as $contractor) {
            $hits[] = [
                'id' => (int) $contractor->getKey(),
                'title' => $contractor->displayName(),
                'subtitle' => trim(implode(' · ', array_filter([
                    $contractor->tax_id === null ? null : 'NIP ' . $contractor->tax_id,
                    $contractor->phone,
                    $contractor->is_active ? null : 'wyłączony',
                ]))),
                'path' => '/contractors',
            ];
        }

        return $this->group('contractors', 'Kontrahenci', 'zlec', $hits);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function products(string $needle): ?array
    {
        if (!$this->allowed(Permission::PRICE_LIST)) {
            return null;
        }

        $products = Product::query()
            ->with('group')
            ->where(static function (Builder $query) use ($needle): void {
                $query
                    ->where('name', 'like', '%' . $needle . '%')
                    ->orWhere('code', 'like', '%' . $needle . '%')
                    ->orWhere('manufacturer_code', 'like', '%' . $needle . '%');
            })
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->limit(self::PER_GROUP)
            ->get();

        $hits = [];

        foreach ($products as $product) {
            $hits[] = [
                'id' => (int) $product->getKey(),
                'title' => $product->name,
                'subtitle' => trim(implode(' · ', array_filter([
                    $product->getAttribute('group')?->name,
                    $product->code,
                ]))),
                'path' => '/price-list?section=' . $product->section->value,
            ];
        }

        return $this->group('products', 'Cennik', 'zlec', $hits);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function users(string $needle): ?array
    {
        if (!$this->allowed(Permission::USERS)) {
            return null;
        }

        $users = User::query()
            ->with('roles')
            ->where(static function (Builder $query) use ($needle): void {
                $query
                    ->where('first_name', 'like', '%' . $needle . '%')
                    ->orWhere('last_name', 'like', '%' . $needle . '%')
                    ->orWhere('email', 'like', '%' . $needle . '%');
            })
            ->orderBy('first_name')
            ->limit(self::PER_GROUP)
            ->get();

        $hits = [];

        foreach ($users as $user) {
            $hits[] = [
                'id' => (int) $user->getKey(),
                'title' => trim($user->first_name . ' ' . ($user->last_name ?? '')),
                'subtitle' => trim(implode(' · ', array_filter([
                    $user->email,
                    $user->roles->pluck('name')->implode(', ') ?: null,
                ]))),
                'path' => '/users',
            ];
        }

        return $this->group('users', 'Użytkownicy', 'adm', $hits);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function locations(string $needle): ?array
    {
        if (!$this->allowed(Permission::DICTIONARIES) && !$this->allowed(Permission::LOCATIONS)) {
            return null;
        }

        $locations = Location::query()
            ->where('name', 'like', '%' . $needle . '%')
            ->orderBy('position')
            ->limit(self::PER_GROUP)
            ->get();

        $hits = [];

        foreach ($locations as $location) {
            $hits[] = [
                'id' => (int) $location->getKey(),
                'title' => $location->name,
                'subtitle' => trim(implode(' · ', array_filter([
                    $location->address_city,
                    $location->is_production ? 'produkcja' : null,
                    $location->is_pickup_point ? 'odbiór' : null,
                ]))),
                'path' => '/dictionaries',
            ];
        }

        return $this->group('locations', 'Lokalizacje', 'adm', $hits);
    }

    /**
     * @param list<array<string, mixed>> $hits
     * @return array<string, mixed>
     */
    private function group(string $key, string $label, string $module, array $hits): array
    {
        return ['key' => $key, 'label' => $label, 'module' => $module, 'hits' => $hits];
    }

    private function allowed(Permission $permission): bool
    {
        $user = Auth::user();

        return $user !== null && $user->can($permission->value . '.list');
    }
}
