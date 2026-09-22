<?php

declare(strict_types=1);

namespace Tests\Feature\Dictionary;

use Tests\TestCase;
use App\Models\GlobalParameter;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Każdy parametr musi mieć gdzie się pokazać.
 *
 * `urgent_surcharge_percent` istniał w seederze, miał typ, opis
 * i działający mechanizm w kalkulatorze — ale nie było go w żadnym
 * paśmie ekranu Parametry, a ekran renderuje wyłącznie to, co w pasmach
 * stoi. Efekt: stawki nie dało się ustawić inaczej niż w bazie,
 * i przez to dopłata za pilne nigdy nie zadziałała.
 *
 * To była **cicha awaria**: nic nie rzucało błędu, nic nie świeciło na
 * czerwono, a funkcja po prostu nie istniała. Ten test zamienia ją na
 * głośną — pasma są w TypeScripcie, więc czytamy je z pliku. Brzydkie,
 * ale tańsze niż drugi taki parametr znaleziony za pół roku.
 */
class ParameterScreenCoverageTest extends TestCase
{
    use RefreshDatabase;

    private const GROUPS = 'frontend/app/src/routes/(app)/parameters/_components/groups.ts';

    #[Test]
    public function kazdy_parametr_stoi_w_ktoryms_pasmie(): void
    {
        $onScreen = $this->keysOnScreen();

        /** @var list<string> $seeded */
        $seeded = GlobalParameter::query()
            ->distinct()
            ->orderBy('key')
            ->pluck('key')
            ->all();

        $this->assertNotEmpty($seeded, 'Seeder parametrów nic nie zasiał.');

        $missing = array_values(array_diff($seeded, $onScreen));

        $this->assertSame([], $missing, sprintf(
            'Parametry bez pasma na ekranie Parametry: %s. '
            . 'Nie da się ich ustawić inaczej niż w bazie.',
            implode(', ', $missing),
        ));
    }

    #[Test]
    public function pasma_nie_wskazuja_parametrow_ktorych_nie_ma(): void
    {
        // Druga strona tego samego rozjazdu: klucz usuniety z seedera,
        // a zostawiony w pasmie, robi na ekranie pusta dziure.
        $onScreen = $this->keysOnScreen();

        /** @var list<string> $seeded */
        $seeded = GlobalParameter::query()->distinct()->pluck('key')->all();

        $orphans = array_values(array_diff($onScreen, $seeded));

        $this->assertSame([], $orphans, sprintf(
            'Pasma wskazują parametry, których nie ma w słowniku: %s.',
            implode(', ', $orphans),
        ));
    }

    /**
     * Klucze wypisane w pasmach.
     *
     * @return list<string>
     */
    private function keysOnScreen(): array
    {
        $path = base_path(self::GROUPS);

        $this->assertFileExists($path, 'Nie ma pliku z pasmami parametrów.');

        $source = (string) file_get_contents($path);

        // Bierzemy wylacznie zawartosc tablic `keys: [...]`, zeby nie
        // zlapac nazw pasm ani kluczy tlumaczen.
        preg_match_all('/keys:\s*\[(.*?)\]/s', $source, $blocks);

        $keys = [];

        foreach ($blocks[1] as $block) {
            preg_match_all("/'([a-z0-9_]+)'/", $block, $found);

            foreach ($found[1] as $key) {
                $keys[$key] = true;
            }
        }

        return array_keys($keys);
    }
}
