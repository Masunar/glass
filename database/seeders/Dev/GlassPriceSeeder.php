<?php

declare(strict_types=1);

namespace Database\Seeders\Dev;

use App\Enum\Section;
use App\Models\Product;
use Salvon\Database\Seeder;
use App\Models\PriceSection;
use App\Services\PriceListService;

/**
 * Macierz cennika szkła dla grupy FLOAT.
 *
 * Bez niej materiał nie ma ceny katalogowej i formatka wychodzi po
 * 0,00 z adnotacją „brak ceny w cenniku" — poprawnie, ale w środowisku
 * deweloperskim nie da się na tym niczego zobaczyć.
 *
 * **Dane deweloperskie, nie referencyjne.** Skąd się biorą liczby:
 *
 * - **Ceny zakupu** (22, 20, 25, 31, 37, 52, 65, 91, 196 zł/m²) siedzą
 *   już w `Core\GlassCatalogSeeder` i są odczytane wprost ze słownika
 *   starego systemu — `50-cennik.md` §4.
 * - **Współczynniki oznaczone niżej jako „dok."** pochodzą z tej samej
 *   dokumentacji i są tam zweryfikowane co do grosza: 22 × 3,8 = 83,60,
 *   37 × 3,5 = 129,50, 52 × 5,0 = 260,00 i szesnaście innych.
 * - **Pozostałe komórki to środek udokumentowanego zakresu sekcji**
 *   z `50-cennik.md` §3 (Detaliczny extra 3,0–5,0 → 4,0; podstawowy
 *   2,8–4,0 → 3,4; stały klient 2,6–3,5 → 3,0; Biznesowy strefa 1
 *   2,4–3,0 → 2,7; strefa 2 2,2–2,6 → 2,4; strefa 3 2,0–2,2 → 2,1).
 *
 * **Przypisanie kolumn jest założeniem.** Dokumentacja podaje pary
 * (grubość, współczynnik), ale nie mówi, do której z sześciu sekcji
 * cenowych należy który współczynnik — `95-silnik-wyceny.md` §5.4.
 * Każdy współczynnik trafił do najwyższej sekcji, której udokumentowany
 * zakres go obejmuje. Dlatego ten seeder stoi w `Dev`, a nie w `Core`:
 * do produkcji macierz wchodzi importem ze starego systemu albo z ręki.
 */
class GlassPriceSeeder extends Seeder
{
    /**
     * Sekcje cenowe szkła od najdroższej. Kolejność jest tu istotna:
     * indeksy wierszy niżej odpowiadają tej liście.
     */
    private const SECTIONS = [
        'Detaliczny extra',
        'Detaliczny podstawowy',
        'Detaliczny stały klient',
        'Biznesowy strefa 1',
        'Biznesowy strefa 2',
        'Biznesowy strefa 3',
    ];

    public function run(): void
    {
        // Nazwa produktu => wspolczynniki w kolejnosci SECTIONS.
        // Wartosci udokumentowane (`50-cennik.md` §4), kazda w najwyzszej
        // sekcji, ktorej zakres z §3 ja obejmuje:
        //   2mm  3,8 / 3,3 / 2,1      3mm  3,8 / 2,4
        //   4mm  3,0 / 2,2            5mm  3,0 / 2,8 / 2,2
        //   6mm  5,0 / 3,5 / 2,6      8mm  5,0 / 2,6 / 2,2
        //  10mm  4,0 / 2,6 / 2,2
        // Pozostale komorki to srodek zakresu danej sekcji. Dla 12 i 15 mm
        // dokumentacja podaje sama cene zakupu, wiec caly wiersz to srodki.
        $matrix = [
            //                 extra  podst.  staly   biz.1   biz.2   biz.3
            'float 2mm' => ['3.8', '3.3', '3.0', '2.7', '2.4', '2.1'],
            'float 3mm' => ['3.8', '3.4', '3.0', '2.7', '2.4', '2.1'],
            'float 4mm' => ['4.0', '3.4', '3.0', '2.7', '2.4', '2.2'],
            'float 5mm' => ['4.0', '3.4', '3.0', '2.8', '2.4', '2.2'],
            'float 6mm' => ['5.0', '3.5', '3.0', '2.6', '2.4', '2.1'],
            'float 8mm' => ['5.0', '3.4', '3.0', '2.6', '2.4', '2.2'],
            'float 10mm' => ['4.0', '3.4', '3.0', '2.6', '2.4', '2.2'],
            'float 12mm' => ['4.0', '3.4', '3.0', '2.7', '2.4', '2.1'],
            'float 15mm' => ['4.0', '3.4', '3.0', '2.7', '2.4', '2.1'],
        ];

        $sections = $this->sections();
        $cells = [];

        foreach ($matrix as $name => $coefficients) {
            $product = Product::query()
                ->where('section', Section::GLASS->value)
                ->where('name', $name)
                ->first();

            if (!$product instanceof Product) {
                continue;
            }

            foreach ($coefficients as $index => $coefficient) {
                $section = $sections[self::SECTIONS[$index]] ?? null;

                if ($section === null) {
                    continue;
                }

                $cells[] = [
                    'product_id' => (int) $product->getKey(),
                    'price_section_id' => $section,
                    'coefficient' => $coefficient,
                ];
            }
        }

        if ($cells !== []) {
            (new PriceListService())->update($cells);
        }
    }

    /**
     * @return array<string, int>
     */
    private function sections(): array
    {
        /** @var iterable<PriceSection> $rows */
        $rows = PriceSection::query()
            ->where('section', Section::GLASS->value)
            ->get();

        $sections = [];

        foreach ($rows as $row) {
            $sections[$row->name] = (int) $row->getKey();
        }

        return $sections;
    }
}
