<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;

/**
 * Ustawienia ekranu zapamiętane przy koncie.
 *
 * **Zamknięta lista kluczy i wartości.** Kolumna `preferences` jest JSON-em,
 * ale nie workiem: zapisuje się wyłącznie to, co jest tu wymienione,
 * i tylko w dozwolonych wartościach. Dowolny klucz z żądania byłby
 * miejscem, w które da się wpisać cokolwiek, i ekranem, który po cichu
 * czyta coś, czego nikt nie sprawdził.
 */
final readonly class UserPreferences
{
    /** Ile zleceń na stronie listy. */
    public const ORDERS_PER_PAGE = 'orders.per_page';

    /** @var array<string, list<int>> */
    private const ALLOWED = [
        self::ORDERS_PER_PAGE => [50, 100, 200],
    ];

    /**
     * Pięćdziesiąt mieści się na ekranie i wczytuje się najszybciej;
     * dwieście było limitem technicznym, nie wyborem.
     *
     * @var array<string, int>
     */
    private const DEFAULTS = [
        self::ORDERS_PER_PAGE => 50,
    ];

    public function get(?User $user, string $key): int
    {
        $stored = $user?->preferences[$key] ?? null;

        return $this->valid($key, $stored) ? (int) $stored : self::DEFAULTS[$key];
    }

    /**
     * Czy wartość jest dozwolona dla klucza. Nieznany klucz nie jest
     * dozwolony niczym.
     */
    public function valid(string $key, mixed $value): bool
    {
        return isset(self::ALLOWED[$key])
            && is_numeric($value)
            && in_array((int) $value, self::ALLOWED[$key], true);
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, list<string>>}
     */
    public function save(User $user, array $input): array
    {
        $preferences = is_array($user->preferences) ? $user->preferences : [];
        $errors = [];

        foreach ($input as $key => $value) {
            if (!$this->valid((string) $key, $value)) {
                $errors[(string) $key] = [isset(self::ALLOWED[$key])
                    ? 'Dozwolone: ' . implode(', ', self::ALLOWED[$key]) . '.'
                    : 'Nieznane ustawienie.'];

                continue;
            }

            $preferences[(string) $key] = (int) $value;
        }

        if ($errors !== []) {
            return ['errors' => $errors];
        }

        $user->preferences = $preferences;
        $user->save();

        return ['errors' => []];
    }
}
