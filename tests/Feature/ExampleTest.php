<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Test dymny API.
 *
 * Zastapil szkieletowy test sprawdzajacy, czy GET "/" zwraca 200 - ten
 * nie mial tu sensu i zawsze konczyl sie porazka, bo aplikacja nie ma
 * trasy webowej pod korzeniem (frontend jest osobna aplikacja SSR).
 * Stale czerwony test uczy ignorowania czerwonego, wiec zamiast niego
 * sprawdzamy, ze routing API i warstwa uwierzytelniania faktycznie dzialaja.
 */
class ExampleTest extends TestCase
{
    public function test_niezalogowany_nie_dostaje_danych_uzytkownika(): void
    {
        $this->getJson('/api/auth/user')->assertStatus(401);
    }
}
