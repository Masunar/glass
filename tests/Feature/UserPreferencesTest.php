<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Services\UserPreferences;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Ustawienia ekranu przy koncie.
 *
 * Kolumna jest JSON-em, więc łatwo zrobić z niej worek. Testy pilnują,
 * że zapisuje się wyłącznie znany klucz w dozwolonej wartości — a
 * odrzucone żądanie niczego nie zmienia.
 */
class UserPreferencesTest extends TestCase
{
    use RefreshDatabase;

    private UserPreferences $preferences;

    protected function setUp(): void
    {
        parent::setUp();

        $this->preferences = new UserPreferences();
    }

    #[Test]
    public function bez_ustawienia_lista_ma_piecdziesiat_wierszy(): void
    {
        $this->assertSame(50, $this->preferences->get($this->user(), UserPreferences::ORDERS_PER_PAGE));
        $this->assertSame(50, $this->preferences->get(null, UserPreferences::ORDERS_PER_PAGE));
    }

    #[Test]
    public function zapamietana_wartosc_wraca_przy_nastepnym_odczycie(): void
    {
        $user = $this->user();

        $this->assertSame([], $this->preferences->save($user, [UserPreferences::ORDERS_PER_PAGE => 100])['errors']);

        // Swiezy model — ustawienie ma byc w bazie, nie w pamieci obiektu.
        $this->assertSame(100, $this->preferences->get($user->fresh(), UserPreferences::ORDERS_PER_PAGE));
    }

    #[Test]
    public function kolejka_i_lista_pamietaja_osobno(): void
    {
        $user = $this->user();

        $this->preferences->save($user, [UserPreferences::PRODUCTION_PER_PAGE => 200]);

        // Biuro i stanowisko to rozne ekrany: wybor na hali nie moze
        // przestawic listy zlecen temu samemu kontu.
        $this->assertSame(200, $this->preferences->get($user->fresh(), UserPreferences::PRODUCTION_PER_PAGE));
        $this->assertSame(50, $this->preferences->get($user->fresh(), UserPreferences::ORDERS_PER_PAGE));
    }

    #[Test]
    public function wartosc_z_zadania_ma_pierwszenstwo_tylko_z_listy(): void
    {
        $user = $this->user();
        $this->preferences->save($user, [UserPreferences::PRODUCTION_PER_PAGE => 100]);

        $this->assertSame(200, $this->preferences->resolve($user, UserPreferences::PRODUCTION_PER_PAGE, '200'));
        $this->assertSame(100, $this->preferences->resolve($user, UserPreferences::PRODUCTION_PER_PAGE, '5000'));
        $this->assertSame(100, $this->preferences->resolve($user, UserPreferences::PRODUCTION_PER_PAGE, null));
    }

    #[Test]
    public function wartosc_spoza_listy_jest_odrzucana_i_niczego_nie_zmienia(): void
    {
        $user = $this->user();
        $this->preferences->save($user, [UserPreferences::ORDERS_PER_PAGE => 100]);

        $result = $this->preferences->save($user, [UserPreferences::ORDERS_PER_PAGE => 5000]);

        $this->assertArrayHasKey(UserPreferences::ORDERS_PER_PAGE, $result['errors']);
        $this->assertSame(100, $this->preferences->get($user->fresh(), UserPreferences::ORDERS_PER_PAGE));
    }

    #[Test]
    public function nieznany_klucz_nie_trafia_do_kolumny(): void
    {
        $user = $this->user();

        $result = $this->preferences->save($user, ['cokolwiek' => 1]);

        $this->assertArrayHasKey('cokolwiek', $result['errors']);
        $this->assertNull($user->fresh()?->preferences);
    }

    private function user(): User
    {
        /** @var User */
        return User::query()->create([
            'first_name' => 'Test',
            'last_name' => 'Ustawienia',
            'email' => 'ustawienia' . random_int(1000, 99999) . '@example.test',
            'password' => 'secret-not-used',
            'is_active' => true,
        ]);
    }
}
