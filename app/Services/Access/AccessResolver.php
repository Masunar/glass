<?php

declare(strict_types=1);

namespace App\Services\Access;

use App\Models\Role;
use App\Models\User;
use App\Support\AccessRegistry;

/**
 * Uprawnienia skuteczne — i skąd każde pochodzi.
 *
 * Pochodzenie nie jest ozdobą ekranu, tylko spłatą za dwa wybory
 * z planu. Paczka jest **wiązaniem** (U-07), a uprawnienia da się nadać
 * **ponad rolę** (U-05) — obie rzeczy dają więcej władzy i obie psują
 * odpowiedź na pytanie „dlaczego on to widzi". Bez podania źródła
 * audyt uprawnień przestaje być przeglądem siedmiu ról.
 *
 * Suma, nie różnica: nadania się dokładają. Odbierania przy
 * użytkowniku świadomie nie ma — Marcin wybrał nadpisanie w górę,
 * a mieszanie obu kierunków dawałoby stan, w którym o dostępie
 * decyduje kolejność czytania reguł.
 */
final readonly class AccessResolver
{
    public const FROM_ROLE = 'role';
    public const FROM_PACKAGE = 'package';
    public const FROM_DIRECT = 'direct';
    public const FROM_SUPERUSER = 'superuser';

    /**
     * Uprawnienia roli: nadane wprost plus wszystkie z jej paczek.
     *
     * @return array<string, list<array{from: string, name: string|null}>>
     */
    public function forRole(Role $role): array
    {
        $sources = [];

        if ($role->is_superuser) {
            // Rola nadrzedna omija sprawdzanie w calosci (`Gate::before`),
            // wiec wypisywanie jej uprawnien po jednym bylo by fikcja:
            // ma wszystko, takze to, co powstanie jutro.
            foreach (AccessRegistry::names() as $name) {
                $sources[$name] = [['from' => self::FROM_SUPERUSER, 'name' => $role->name]];
            }

            return $sources;
        }

        foreach ($role->permissions as $permission) {
            $sources[$permission->name][] = ['from' => self::FROM_ROLE, 'name' => $role->name];
        }

        foreach ($role->packages as $package) {
            foreach ($package->permissions as $permission) {
                $sources[$permission->name][] = [
                    'from' => self::FROM_PACKAGE,
                    'name' => $package->name,
                ];
            }
        }

        return $sources;
    }

    /**
     * Uprawnienia użytkownika: z każdej roli, z paczek tych ról
     * i nadane bezpośrednio.
     *
     * @return array<string, list<array{from: string, name: string|null}>>
     */
    public function forUser(User $user): array
    {
        $sources = [];

        /** @var Role $role */
        foreach ($user->roles as $role) {
            foreach ($this->forRole($role) as $name => $origins) {
                foreach ($origins as $origin) {
                    $sources[$name][] = $origin;
                }
            }
        }

        foreach ($user->permissions as $permission) {
            $sources[$permission->name][] = ['from' => self::FROM_DIRECT, 'name' => null];
        }

        return $sources;
    }

    /**
     * Same nazwy, bez pochodzenia — do sprawdzeń i porównań.
     *
     * @return list<string>
     */
    public function namesForUser(User $user): array
    {
        return array_keys($this->forUser($user));
    }

    /**
     * Czytelny opis pochodzenia dla ekranu.
     *
     * @param list<array{from: string, name: string|null}> $origins
     */
    public function describe(array $origins): string
    {
        $parts = [];

        foreach ($origins as $origin) {
            $parts[] = match ($origin['from']) {
                self::FROM_SUPERUSER => 'rola nadrzędna ' . $origin['name'],
                self::FROM_ROLE => 'rola ' . $origin['name'],
                self::FROM_PACKAGE => 'paczka ' . $origin['name'],
                default => 'nadane wprost',
            };
        }

        return implode(', ', array_unique($parts));
    }
}
