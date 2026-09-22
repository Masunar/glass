<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use Tests\TestCase;
use App\Models\AppPage;
use App\Enum\Permission as AppPermission;
use App\Support\AccessRegistry;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Pokrycie rejestru — nasz odpowiednik kafelka „75 uprawnień bez
 * przypisanej strony".
 *
 * Wzorzec, z którego wzięliśmy ten model, pokazywał sieroty uczciwie
 * i tym samym pozwalał im rosnąć. U nas mają **wywalać CI**. To ta sama
 * rodzina co `ParameterScreenCoverageTest`: parametr, który istniał
 * w bazie i nie dawał się ustawić, bo nikt nie przypisał go do pasma.
 */
class AccessCoverageTest extends TestCase
{
    use RefreshDatabase;

    private const ROUTER = 'frontend/app/src/router/app-router.ts';

    #[Test]
    public function kazde_uprawnienie_z_enuma_jest_w_rejestrze(): void
    {
        foreach (AppPermission::cases() as $case) {
            $this->assertArrayHasKey(
                $case->value,
                AccessRegistry::permissions(),
                sprintf(
                    'Uprawnienie "%s" jest w enumie, ale nie ma go w rejestrze. '
                    . 'Dopisz je razem z modułem i stroną, albo oznacz jako PLANNED z powodem.',
                    $case->value,
                ),
            );
        }
    }

    #[Test]
    public function rejestr_nie_wymysla_uprawnien_spoza_enuma(): void
    {
        $known = array_map(
            static fn(AppPermission $case): string => $case->value,
            AppPermission::cases(),
        );

        foreach (array_keys(AccessRegistry::permissions()) as $key) {
            $this->assertContains($key, $known, sprintf(
                'Rejestr zna uprawnienie "%s", którego nie ma w App\Enum\Permission.',
                $key,
            ));
        }
    }

    /**
     * To jest ten test, ktory nie pozwala uzbierac sierot: uprawnienie
     * dzialajace bez ekranu to uprawnienie, ktorego nikt nie bedzie
     * umial nadac swiadomie.
     *
     * Naruszenia zbieramy do listy zamiast przerywac na pierwszym —
     * przy dwudziestu wpisach chcemy zobaczyc wszystkie naraz, a nie
     * poprawiac je po jednym przez piec przebiegow.
     */
    #[Test]
    public function dzialajace_uprawnienie_ma_strone(): void
    {
        $withoutPage = [];
        $unknownPage = [];

        foreach (AccessRegistry::permissions() as $key => $definition) {
            if ($definition['state'] !== AccessRegistry::ACTIVE) {
                continue;
            }

            $page = $definition['page'];

            if ($page === null) {
                $withoutPage[] = $key;

                continue;
            }

            if (!array_key_exists($page, AccessRegistry::pages())) {
                $unknownPage[] = $key . ' → ' . $page;
            }
        }

        $this->assertSame([], $withoutPage, 'Uprawnienia działające bez wskazanej strony.');
        $this->assertSame([], $unknownPage, 'Uprawnienia wskazujące nieistniejącą stronę.');
    }

    #[Test]
    public function zaplanowane_uprawnienie_ma_powod(): void
    {
        $withoutReason = [];

        foreach (AccessRegistry::permissions() as $key => $definition) {
            if ($definition['state'] !== AccessRegistry::PLANNED) {
                continue;
            }

            // Bez sprawdzania `null`: kontrakt rejestru mowi `string`,
            // wiec brakiem powodu jest pusty napis, nie brak wartosci.
            if (trim($definition['note']) === '') {
                $withoutReason[] = $key;
            }
        }

        // „Zaplanowane" bez powodu jest nie do odroznienia od
        // przeoczenia — a wtedy nikt go nigdy nie sprzatnie.
        $this->assertSame([], $withoutReason, 'Uprawnienia zaplanowane, które nie mówią dlaczego.');
    }

    #[Test]
    public function kazda_trasa_frontu_ma_strone_w_rejestrze(): void
    {
        $paths = $this->routerPaths();

        $this->assertNotEmpty($paths, 'Nie udało się odczytać tras z ' . self::ROUTER);

        $known = array_column(AccessRegistry::pages(), 'path');

        foreach ($paths as $path) {
            $this->assertContains($path, $known, sprintf(
                'Trasa "%s" istnieje w app-router.ts, ale nie ma jej w AccessRegistry::PAGES. '
                . 'Nowy ekran bez wpisu jest ekranem, którego nie da się nikomu nadać.',
                $path,
            ));
        }
    }

    #[Test]
    public function rejestr_nie_wymysla_stron_spoza_routera(): void
    {
        $paths = $this->routerPaths();

        foreach (AccessRegistry::pages() as $code => $page) {
            $this->assertContains($page['path'], $paths, sprintf(
                'Rejestr zna stronę "%s" (%s), której nie ma w app-router.ts.',
                $code,
                $page['path'],
            ));
        }
    }

    #[Test]
    public function zasiew_zaklada_uprawnienia_i_strony(): void
    {
        foreach (AccessRegistry::names() as $name) {
            $this->assertDatabaseHas('permissions', ['name' => $name, 'guard_name' => 'web']);
        }

        $this->assertSame(
            count(AccessRegistry::pages()),
            AppPage::query()->count(),
        );
    }

    #[Test]
    public function zasiew_kasuje_uprawnienie_spoza_rejestru(): void
    {
        Permission::query()->create(['name' => 'sierota.list', 'guard_name' => 'web']);

        $this->seed(\Database\Seeders\Core\PermissionSeeder::class);

        // Uprawnienie spoza rejestru nie ma czego chronic, a nadac je
        // nadal mozna — wiec znika, zamiast czekac w kafelku.
        $this->assertDatabaseMissing('permissions', ['name' => 'sierota.list']);
    }

    #[Test]
    public function zasiew_nie_nadpisuje_przypisania_zmienionego_recznie(): void
    {
        /** @var AppPage $page */
        $page = AppPage::query()->where('code', 'price_list')->firstOrFail();

        /** @var Permission $other */
        $other = Permission::query()->where('name', 'orders.list')->firstOrFail();

        $page->update(['permission_id' => $other->getKey()]);

        $this->seed(\Database\Seeders\Core\PermissionSeeder::class);

        // Administrator moze zmienic przypisanie bez wdrozenia (U-01),
        // wiec seeder nie ma prawa cofac jego decyzji przy kazdym
        // kolejnym wdrozeniu.
        $this->assertSame((int) $other->getKey(), (int) $page->refresh()->permission_id);
    }

    /**
     * Każde uprawnienie sprawdzane przez kontroler istnieje w rejestrze.
     *
     * To jest ta sama rodzina co sieroty, tylko z drugiej strony:
     * `protect()` na nazwie spoza rejestru nie daje odmowy, tylko
     * **wyjątek** — spatie rzuca `PermissionDoesNotExist`, więc każdy
     * poza rolą nadrzędną dostaje błąd serwera zamiast „brak dostępu".
     * Awaria cicha do chwili, gdy pierwszy nie-administrator kliknie.
     *
     * Czytamy zarejestrowane pośredniki, nie kod źródłowy: `protect()`
     * bywa wołane z `$this->permission`, więc regexp po plikach
     * zgadywałby wartość, którą kontener zna na pewno.
     */
    #[Test]
    public function kontrolery_chronia_sie_uprawnieniami_z_rejestru(): void
    {
        $known = AccessRegistry::names();
        $unknown = [];
        $broken = [];

        foreach ($this->controllers() as $class) {
            try {
                $controller = app($class);
            } catch (\Throwable $exception) {
                $broken[] = $class . ': ' . $exception->getMessage();

                continue;
            }

            if (!method_exists($controller, 'getMiddleware')) {
                continue;
            }

            /** @var list<array{middleware: mixed}> $middleware */
            $middleware = $controller->getMiddleware();

            foreach ($middleware as $entry) {
                $name = $entry['middleware'];

                if (!is_string($name) || !str_starts_with($name, 'permission:')) {
                    continue;
                }

                $permission = substr($name, strlen('permission:'));

                if (!in_array($permission, $known, true)) {
                    $unknown[] = $class . ' → ' . $permission;
                }
            }
        }

        $this->assertSame([], $broken, 'Kontrolerów nie dało się utworzyć.');
        $this->assertSame(
            [],
            $unknown,
            'Uprawnienia sprawdzane przez kontroler, których nie ma w rejestrze. '
            . 'Takie `protect()` daje błąd serwera, nie odmowę.',
        );
    }

    /**
     * Klasy kontrolerów aplikacji.
     *
     * @return list<class-string>
     */
    private function controllers(): array
    {
        /** @var list<string> $files */
        $files = glob(app_path('Http/Controllers/*.php')) ?: [];
        $classes = [];

        foreach ($files as $file) {
            /** @var class-string $class */
            $class = 'App\\Http\\Controllers\\' . basename($file, '.php');

            if (!class_exists($class)) {
                continue;
            }

            $reflection = new \ReflectionClass($class);

            if ($reflection->isAbstract()) {
                continue;
            }

            $classes[] = $class;
        }

        return $classes;
    }

    /**
     * Ścieżki tras odczytane z `app-router.ts`.
     *
     * Parsowanie pliku frontu z testu PHP wygląda na hack i nim jest —
     * ale to jedyny sposób, żeby nowa trasa nie mogła powstać bez wpisu
     * w rejestrze. Ten sam chwyt pilnuje pasm na ekranie parametrów.
     *
     * @return list<string>
     */
    private function routerPaths(): array
    {
        $source = (string) file_get_contents(base_path(self::ROUTER));

        preg_match_all("/path:\s*'([^']+)'/", $source, $matches);

        /** @var list<string> */
        return array_values(array_unique($matches[1]));
    }
}
