<?php

declare(strict_types=1);

namespace Database\Seeders\Dev;

use App\Enum\Section;
use App\Models\Product;
use Salvon\Database\Seeder;
use App\Models\PriceSection;
use App\Services\PriceListService;

/**
 * Macierz cennika szkła.
 *
 * Bez niej materiał nie ma ceny katalogowej i formatka wychodzi bez
 * kwoty — poprawnie, bo brak ceny nie jest zerem, ale w środowisku
 * deweloperskim nie da się na tym niczego zobaczyć: dopłata za kształt
 * czy gabaryt to mnożnik ceny materiału, więc dopóki materiał nie ma
 * ceny, przełączniki w panelu nie mają na co działać.
 *
 * **Dane deweloperskie, nie referencyjne.** Skąd biorą się liczby:
 *
 * - **Ceny zakupu** (22, 20, 25, 31, 37, 52, 65, 91, 196 zł/m²) siedzą
 *   w `Core\GlassCatalogSeeder` i są odczytane wprost ze słownika
 *   starego systemu — `50-cennik.md` §4.
 * - **Współczynniki oznaczone „dok."** pochodzą z tej samej
 *   dokumentacji i są tam zweryfikowane co do grosza: 22 × 3,8 = 83,60,
 *   37 × 3,5 = 129,50, 52 × 5,0 = 260,00 i szesnaście innych.
 * - **Pozostałe komórki to środek udokumentowanego zakresu sekcji**
 *   z `50-cennik.md` §3.
 *
 * **Przypisanie kolumn jest założeniem.** Dokumentacja podaje pary
 * (grubość, współczynnik), ale nie mówi, do której z sześciu sekcji
 * cenowych należy który współczynnik — `95-silnik-wyceny.md` §5.4.
 * Każdy współczynnik trafił do najwyższej sekcji, której udokumentowany
 * zakres go obejmuje. Dlatego seeder stoi w `Dev`, a nie w `Core`.
 *
 * **Współczynnik to polityka marży, nie własność materiału**, więc
 * drabinka idzie po grubości i obowiązuje każde szkło tej grubości —
 * także takie, którego w dokumentacji nie ma. Szkło bez ceny zakupu
 * (lustra) dostaje komórkę cennika, ale nadal nie ma ceny i mówi
 * o tym wprost: nie ma czego mnożyć.
 */
class GlassPriceSeeder extends Seeder
{
    /** Sekcje cenowe szkła od najdroższej — kolejność odpowiada drabinkom. */
    private const SECTIONS = [
        'Detaliczny extra',
        'Detaliczny podstawowy',
        'Detaliczny stały klient',
        'Biznesowy strefa 1',
        'Biznesowy strefa 2',
        'Biznesowy strefa 3',
    ];

    /**
     * Środki zakresów z `50-cennik.md` §3 — drabinka dla grubości,
     * której dokumentacja nie opisuje.
     *
     * @var list<string>
     */
    private const LADDER = ['4.0', '3.4', '3.0', '2.7', '2.4', '2.1'];

    public function run(): void
    {
        $sections = $this->sections();

        if ($sections === []) {
            return;
        }

        $cells = [];

        /** @var iterable<Product> $products */
        $products = Product::query()
            ->with('glass')
            ->where('section', Section::GLASS->value)
            ->get();

        foreach ($products as $product) {
            $thickness = $product->glass?->thickness_mm;
            $ladder = $this->ladder($thickness === null ? null : (float) $thickness);

            foreach ($ladder as $index => $coefficient) {
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
     * Drabinka współczynników dla grubości. Wartości udokumentowane
     * (`50-cennik.md` §4), każda w najwyższej sekcji, której zakres
     * z §3 ją obejmuje:
     *
     *   2 mm  3,8 / 3,3 / 2,1        3 mm  3,8 / 2,4
     *   4 mm  3,0 / 2,2              5 mm  3,0 / 2,8 / 2,2
     *   6 mm  5,0 / 3,5 / 2,6        8 mm  5,0 / 2,6 / 2,2
     *  10 mm  4,0 / 2,6 / 2,2
     *
     * Dla 12 i 15 mm dokumentacja podaje samą cenę zakupu, więc cały
     * wiersz to środki zakresów — tak samo jak dla grubości spoza listy.
     *
     * @return list<string>
     */
    private function ladder(?float $thickness): array
    {
        $documented = [
            //        extra  podst.  staly  biz.1  biz.2  biz.3
            '2' => ['3.8', '3.3', '3.0', '2.7', '2.4', '2.1'],
            '3' => ['3.8', '3.4', '3.0', '2.7', '2.4', '2.1'],
            '4' => ['4.0', '3.4', '3.0', '2.7', '2.4', '2.2'],
            '5' => ['4.0', '3.4', '3.0', '2.8', '2.4', '2.2'],
            '6' => ['5.0', '3.5', '3.0', '2.6', '2.4', '2.1'],
            '8' => ['5.0', '3.4', '3.0', '2.6', '2.4', '2.2'],
            '10' => ['4.0', '3.4', '3.0', '2.6', '2.4', '2.2'],
        ];

        if ($thickness === null) {
            return self::LADDER;
        }

        // Klucze dopasowania bywają ułamkowe (ornament 8 mm to 8,05),
        // więc porównujemy liczbami, a nie napisami.
        foreach ($documented as $key => $ladder) {
            if (abs((float) $key - $thickness) < 0.001) {
                return $ladder;
            }
        }

        return self::LADDER;
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
