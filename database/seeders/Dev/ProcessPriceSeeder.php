<?php

declare(strict_types=1);

namespace Database\Seeders\Dev;

use Carbon\Carbon;
use App\Enum\Unit;
use App\Enum\Section;
use App\Models\Process;
use App\Models\Product;
use Salvon\Database\Seeder;
use App\Models\ProductGroup;
use App\Models\PriceSection;
use App\Models\PurchasePrice;
use App\Models\ProductService;
use App\Enum\PurchasePriceSource;
use App\Services\PriceListService;

/**
 * Pozycje cennikowe procesów — zakładka „Rodzaj" słownika Usług.
 *
 * Bez nich wybór pozycji przy etapie nie ma z czego wybierać, a proces
 * zostaje niewyceniony. Wartości przepisane ze słownika starego systemu.
 *
 * **Dane deweloperskie, nie referencyjne.** Przepisane z pięciu zrzutów
 * ekranu, więc obejmują pięć procesów z czternastu — komplet przyjdzie
 * ze zrzutem starej bazy. Do czasu, aż będzie pełny, ten seeder nie ma
 * czego robić w danych rdzeniowych: testy sieją dane referencyjne
 * i sto pięćdziesiąt produktów zmieniłoby im punkt wyjścia.
 *
 * **`Gr. szkła` to klucz dopasowania, nie wymiar.** Ułamki i duże liczby
 * są tam celowo: ornament 8 mm ma 8,05, żeby odróżnić się od zwykłej
 * ósemki; VSG 66.2 ma 12,8; szyba zespolona 98; mozaika 88 i 89;
 * a 99 znaczy „nie zależy od grubości" (CNC).
 *
 * **Grubość zawęża listę, ale jej nie rozstrzyga.** Fazowanie ma dla
 * ośmiomilimetrowej szyby osiem wierszy — faza od 5 do 40 mm — więc
 * wyboru między nimi musi dokonać człowiek.
 *
 * **Czego tu nie ma.** Hartownia, laminacja, lakier, wiercenie, oprawa
 * obrazu, wydruk, montaż, inne i nietypowe — tych cenników nie
 * dostaliśmy, więc ich nie ma. Proces bez pozycji jest niewyceniony
 * i mówi o tym wprost; zmyślona cena wyglądałaby tak samo jak prawdziwa.
 *
 * **Współczynnik 1,0 jest zaślepką.** Kolumna w starym słowniku to cena
 * **zakupu**, a marży procesów nie dostaliśmy — w zleceniu widać raz
 * kwotę niższą od zakupowej, raz wyższą, więc z dwóch przykładów nie da
 * się jej odtworzyć. Do ustalenia razem z cennikiem usług.
 */
class ProcessPriceSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ...$this->cutting(),
            ...$this->grinding(),
            ...$this->polishing(),
            ...$this->bevelling(),
            ...$this->cnc(),
        ];

        $cells = [];

        foreach ($rows as [$code, $name, $thickness, $purchase, $unit]) {
            $product = $this->position($code, $name, $thickness, $purchase, $unit);

            if ($product === null) {
                continue;
            }

            $cells[] = [
                'product_id' => (int) $product->getKey(),
                'price_section_id' => (int) $this->section()->getKey(),
                'coefficient' => '1.0',
            ];
        }

        if ($cells !== []) {
            (new PriceListService())->update($cells);
        }
    }

    /**
     * Cięcie — jeden wiersz na grubość, więc dopasowanie jest
     * jednoznaczne i automat może wybrać sam.
     *
     * @return list<array{0: string, 1: string, 2: float|null, 3: string, 4: Unit}>
     */
    private function cutting(): array
    {
        $mb = Unit::RUNNING_METER;

        return [
            ['C', 'Cięcie 2mm', 2.0, '0.50', $mb],
            ['C', 'Cięcie 3mm', 3.0, '0.50', $mb],
            ['C', 'Cięcie 4mm', 4.0, '0.50', $mb],
            ['C', 'Cięcie ornament 4mm', 4.05, '1.00', $mb],
            ['C', 'Cięcie VSG 4,4', 4.4, '1.00', $mb],
            ['C', 'Cięcie 5mm', 5.0, '0.50', $mb],
            ['C', 'Cięcie 6mm', 6.0, '0.50', $mb],
            ['C', 'Cięcie ornament 6mm', 6.05, '1.00', $mb],
            ['C', 'Cięcie VSG 6,4', 6.4, '1.00', $mb],
            ['C', 'Cięcie 8mm', 8.0, '0.75', $mb],
            ['C', 'Cięcie ornament 8mm', 8.05, '1.50', $mb],
            ['C', 'Cięcie VSG 8,4', 8.4, '1.00', $mb],
            ['C', 'Cięcie VSG 8,8', 8.8, '1.00', $mb],
            ['C', 'Cięcie VSG 9,2', 9.2, '1.00', $mb],
            ['C', 'Cięcie VSG 9,6', 9.6, '1.00', $mb],
            ['C', 'Cięcie 10mm', 10.0, '0.75', $mb],
            ['C', 'Cięcie ornament 10mm', 10.05, '1.50', $mb],
            ['C', 'Cięcie VSG 10,4', 10.4, '1.00', $mb],
            ['C', 'Cięcie VSG 10,8', 10.8, '1.00', $mb],
            ['C', 'Cięcie 12mm', 12.0, '0.75', $mb],
            ['C', 'Cięcie VSG 12,4', 12.4, '1.00', $mb],
            ['C', 'Cięcie VSG 66.2', 12.8, '1.25', $mb],
            ['C', 'Cięcie 15mm', 15.0, '1.00', $mb],
            ['C', 'Cięcie VSG 88.2', 16.8, '2.00', $mb],
            ['C', 'Cięcie VSG 8.8,4', 17.52, '2.00', $mb],
            ['C', 'Cięcie 19mm', 19.0, '2.00', $mb],
            // Zespolenie nie jest cieciem i kosztuje zero — wiersz
            // istnieje po to, zeby szyba zespolona miala co dopasowac.
            ['C', 'Cięcie szyba zespolona', 98.0, '0.00', $mb],
        ];
    }

    /**
     * @return list<array{0: string, 1: string, 2: float|null, 3: string, 4: Unit}>
     */
    private function grinding(): array
    {
        $mb = Unit::RUNNING_METER;

        return [
            ['S', 'Szlif blachy', 1.0, '7.00', $mb],
            ['S', 'Szlif 2mm', 2.0, '6.00', $mb],
            ['S', 'Szlif 3mm', 3.0, '3.00', $mb],
            ['S', 'Szlif 4mm', 4.0, '2.00', $mb],
            ['S', 'Szlif ornamentu 4mm', 4.05, '8.00', $mb],
            ['S', 'Szlif VSG 4,4', 4.4, '5.00', $mb],
            ['S', 'Szlif 5mm', 5.0, '2.25', $mb],
            ['S', 'Szlif 6mm', 6.0, '2.25', $mb],
            ['S', 'Szlif ornamentu 6mm', 6.05, '12.00', $mb],
            ['S', 'Szlif VSG 6,4', 6.4, '3.00', $mb],
            ['S', 'Szlif 8mm', 8.0, '3.25', $mb],
            ['S', 'Szlif ornamentu 8mm', 8.05, '16.00', $mb],
            ['S', 'Szlif VSG 8,4', 8.4, '3.50', $mb],
            ['S', 'Szlif VSG 8,8', 8.8, '4.00', $mb],
            ['S', 'Szlif VSG 9,2', 9.2, '4.50', $mb],
            ['S', 'Szlif VSG/ESG 44.4 (9,52)', 9.52, '5.50', $mb],
            ['S', 'Szlif VSG 9,6', 9.6, '4.50', $mb],
            ['S', 'Szlif 10mm', 10.0, '3.75', $mb],
            ['S', 'Szlif ornamentu 10mm', 10.05, '20.00', $mb],
            ['S', 'Szlif VSG 10,4', 10.4, '4.50', $mb],
            ['S', 'Szlif VSG/ESG 55.2 (10,76)', 10.76, '6.00', $mb],
            ['S', 'Szlif VSG 10,8', 10.8, '5.00', $mb],
            ['S', 'Szlif VSG/ESG 55.4 (11,52)', 11.52, '6.00', $mb],
            ['S', 'Szlif 12mm', 12.0, '4.00', $mb],
            ['S', 'Szlif VSG 12,4', 12.4, '5.00', $mb],
            ['S', 'Szlif VSG/ESG 66.2 (12,76)', 12.76, '7.00', $mb],
            ['S', 'Szlif VSG 12,8', 12.8, '6.00', $mb],
            ['S', 'Szlif VSG/ESG 66.4 (13,52)', 13.52, '7.00', $mb],
            ['S', 'Szlif 15mm', 15.0, '6.00', $mb],
            ['S', 'Szlif VSG/ESG 88.2 (16,76)', 16.76, '8.00', $mb],
            ['S', 'Szlif VSG 88.2', 16.8, '12.00', $mb],
            ['S', 'Szlif VSG/ESG 88.4 (17,52)', 17.52, '8.00', $mb],
            ['S', 'Szlif 19mm', 19.0, '12.00', $mb],
            ['S', 'Szlif VSG/ESG 1010.4 (21,52)', 21.52, '9.25', $mb],
            ['S', 'Szlif VSG 888.22', 25.52, '12.00', $mb],
            ['S', 'Mozaika szlif', 89.0, '8.00', $mb],
        ];
    }

    /**
     * @return list<array{0: string, 1: string, 2: float|null, 3: string, 4: Unit}>
     */
    private function polishing(): array
    {
        $mb = Unit::RUNNING_METER;

        return [
            ['P', 'Poler 2mm', 2.0, '10.00', $mb],
            ['P', 'Poler 3mm', 3.0, '3.00', $mb],
            ['P', 'Poler 4mm', 4.0, '2.50', $mb],
            ['P', 'Poler ornament 4mm', 4.05, '3.75', $mb],
            ['P', 'Poler VSG 4,4', 4.4, '6.00', $mb],
            ['P', 'Poler 5mm', 5.0, '3.00', $mb],
            ['P', 'Poler 6mm', 6.0, '3.00', $mb],
            ['P', 'Poler ornament 6mm', 6.05, '4.50', $mb],
            ['P', 'Poler VSG 6,4', 6.4, '4.00', $mb],
            ['P', 'Poler 8mm', 8.0, '3.75', $mb],
            ['P', 'Poler ornament 8mm', 8.05, '6.00', $mb],
            ['P', 'Poler VSG 8,4', 8.4, '4.50', $mb],
            ['P', 'Poler VSG/ESG 8,76', 8.76, '11.00', $mb],
            ['P', 'Poler VSG 8,8', 8.8, '5.00', $mb],
            ['P', 'Poler VSG 9,2', 9.2, '6.00', $mb],
            ['P', 'Poler VSG/ESG 44.4 (9,52)', 9.52, '11.00', $mb],
            ['P', 'Poler VSG 9,6', 9.6, '6.00', $mb],
            ['P', 'Poler 10mm', 10.0, '4.50', $mb],
            ['P', 'Poler ornament 10mm', 10.05, '7.50', $mb],
            ['P', 'Poler VSG 10,4', 10.4, '6.00', $mb],
            ['P', 'Poler VSG/ESG 55.2 (10,76)', 10.76, '12.00', $mb],
            ['P', 'Poler VSG 10,8', 10.8, '6.50', $mb],
            ['P', 'Poler VSG/ESG 55.4 (11,52)', 11.52, '12.00', $mb],
            ['P', 'Poler 12mm', 12.0, '5.50', $mb],
            ['P', 'Poler VSG 12,4', 12.4, '6.50', $mb],
            ['P', 'Poler VSG/ESG 66.2 (12,76)', 12.76, '14.00', $mb],
            // W slowniku sa dwa wiersze „Poler VSG 66.2" o tej samej
            // grubosci i roznej cenie (7,50 i 7,00). Zostawiamy jeden:
            // duplikat znaczylby tylko tyle, ze automat nie ma jak
            // wybrac, a czlowiek nie widzi roznicy miedzy nimi.
            ['P', 'Poler VSG 66.2', 12.8, '7.50', $mb],
            ['P', 'Poler VSG/ESG 66.4 (13,52)', 13.52, '14.00', $mb],
            ['P', 'Poler 15mm', 15.0, '7.50', $mb],
            ['P', 'Poler VSG/ESG 88.2 (16,76)', 16.76, '16.00', $mb],
            ['P', 'Poler VSG 88.2', 16.8, '14.00', $mb],
            ['P', 'Poler VSG/ESG 88.4 (17,52)', 17.52, '16.00', $mb],
            ['P', 'Poler 19mm', 19.0, '14.00', $mb],
            ['P', 'Poler VSG/ESG 1010.4 (21,52)', 21.52, '20.00', $mb],
            ['P', 'Poler VSG 888.22', 25.52, '24.00', $mb],
        ];
    }

    /**
     * Fazowanie — po kilka wierszy na grubość, różniących się
     * szerokością fazy. To jest ten proces, przy którym automatyczny
     * wybór jest niemożliwy i decydować musi człowiek.
     *
     * @return list<array{0: string, 1: string, 2: float|null, 3: string, 4: Unit}>
     */
    private function bevelling(): array
    {
        $mb = Unit::RUNNING_METER;

        // szerokosc fazy => cena, ta sama dla kazdej grubosci
        $widths = [
            5 => '5.00', 10 => '6.00', 15 => '8.00', 20 => '10.00',
            25 => '14.00', 30 => '17.00', 35 => '17.50', 40 => '22.50',
        ];

        // grubosc => najszersza faza dostepna dla tej grubosci
        $available = [4 => 20, 5 => 30, 6 => 30, 8 => 40, 10 => 40, 12 => 40];

        $rows = [];

        foreach ($available as $thickness => $maxWidth) {
            foreach ($widths as $width => $price) {
                if ($width > $maxWidth) {
                    continue;
                }

                $rows[] = ['F', 'Faza ' . $width . 'mm', (float) $thickness, $price, $mb];
            }
        }

        foreach ([10 => '100.00', 15 => '150.00', 20 => '190.00', 25 => '250.00'] as $width => $price) {
            $rows[] = ['F', 'Mozaika faza ' . $width . 'mm', 88.0, $price, $mb];
        }

        return $rows;
    }

    /**
     * CNC — cztery pozycje z grubością 99, czyli od grubości
     * niezależne. Rozliczane na sztuki, nie na metry.
     *
     * @return list<array{0: string, 1: string, 2: float|null, 3: string, 4: Unit}>
     */
    private function cnc(): array
    {
        $pcs = Unit::PIECE;

        return [
            ['R', 'CNC faza/grawer 30min', 99.0, '100.00', $pcs],
            ['R', 'CNC faza/grawer 60min', 99.0, '150.00', $pcs],
            ['R', 'CNC 3os. 30min', 99.0, '70.00', $pcs],
            ['R', 'CNC 3os. 60min', 99.0, '120.00', $pcs],
        ];
    }

    private function position(
        string $code,
        string $name,
        ?float $thickness,
        string $purchase,
        Unit $unit,
    ): ?Product {
        $process = Process::findByCode($code);

        if ($process === null) {
            return null;
        }

        /** @var Product $product */
        $product = Product::query()->firstOrCreate(
            ['section' => Section::SERVICES->value, 'name' => $name],
            [
                'product_group_id' => (int) $this->group($process->name)->getKey(),
                'section' => Section::SERVICES->value,
                'name' => $name,
                'unit' => $unit->value,
                'vat_rate' => 23,
            ],
        );

        ProductService::query()->firstOrCreate(
            ['product_id' => $product->id],
            [
                'product_id' => $product->id,
                'process_id' => $process->id,
                'glass_thickness_mm' => $thickness,
            ],
        );

        PurchasePrice::query()->firstOrCreate(
            ['product_id' => $product->id, 'valid_from' => Carbon::today()->startOfYear()],
            [
                'product_id' => $product->id,
                'net_price' => $purchase,
                'source' => PurchasePriceSource::MANUAL->value,
                'valid_from' => Carbon::today()->startOfYear(),
            ],
        );

        return $product;
    }

    /** Grupa produktowa per proces — tak jak lista po lewej w słowniku. */
    private function group(string $name): ProductGroup
    {
        /** @var ProductGroup */
        return ProductGroup::query()->firstOrCreate(
            ['section' => Section::SERVICES->value, 'name' => $name],
            ['section' => Section::SERVICES->value, 'name' => $name, 'position' => 0],
        );
    }

    private function section(): PriceSection
    {
        /** @var PriceSection */
        return PriceSection::query()
            ->where('section', Section::SERVICES->value)
            ->where('is_default', true)
            ->firstOrFail();
    }
}
