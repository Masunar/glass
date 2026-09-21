<?php

declare(strict_types=1);

namespace Database\Seeders\Dev;

use App\Enum\Unit;
use App\Enum\Section;
use App\Models\Product;
use Salvon\Database\Seeder;
use App\Models\FittingSet;
use App\Models\ProductGroup;
use App\Models\PriceSection;
use App\Models\PurchasePrice;
use App\Models\ProductFitting;
use App\Models\FittingSetItem;
use App\Enum\PurchasePriceSource;
use App\Services\PriceListService;
use App\Services\Warehouse\StockLedger;

/**
 * Okucia, ich stany i biblioteka zestawów.
 *
 * **Dane deweloperskie.** Wszystko poniżej pochodzi ze zrzutów starego
 * systemu, ale nie wszystko jest tam kompletne — i te braki są opisane
 * przy każdej porcji z osobna, zamiast być zasypane wymyśloną wartością.
 *
 * Seeder pokazuje też, że stan magazynowy **nie jest polem**: stany
 * początkowe wchodzą przez inwentaryzację (`StockLedger::count()`),
 * więc w rejestrze zostaje ślad „skąd się wzięło dwanaście sztuk".
 */
class FittingSeeder extends Seeder
{
    public function run(): void
    {
        $group = $this->group();
        $ledger = new StockLedger();

        // Kod, stan, min, max — przepisane wprost z dwoch zrzutow:
        // `40-magazyn.md` par. 3.2 (siedem pozycji, na ktorych
        // potwierdzilem wzor min/max) i `80-slowniki.md` par. 3.2 (trzy
        // kolejne, zgodne co do sztuki z modulem Magazyn).
        $stocked = [
            ['TGHU90LH BL', 0, 2, 12],
            ['TGHU90RH SC', 1, 2, 12],
            ['TGHU90RH PC', 2, 4, 12],
            ['TGHU180LH PC', 5, 6, 12],
            ['TGHU90-OSRH PC', 0, 2, 6],
            ['TGHU90LH PC', 6, 4, 12],
            ['TGHU90-OSLH PC', 12, 2, 6],
            ['TGHU180LH BL', 2, 2, 12],
            ['TGHU180RH PC', 1, 6, 12],
            ['TGHU90-OSLH BL', 0, 2, 12],
        ];

        foreach ($stocked as [$code, $stock, $min, $max]) {
            // Ceny zakupu tych pozycji nie ma na zadnym zrzucie, wiec ich
            // nie ma i tutaj. Beda niewycenione i powiedza to wprost —
            // tak samo jak lustra w katalogu szkla.
            $product = $this->product($group, $code, $this->name($code), $this->finish($code), null);

            $ledger->thresholds($product, (float) $min, (float) $max);

            if ($stock > 0) {
                $ledger->count($product, (float) $stock, note: 'stan początkowy ze starego systemu');
            }
        }

        // Te dwie pozycje maja cene zakupu, bo widac ja na zrzucie
        // zestawu `23) Marta Grabowska` (`80-slowniki.md` par. 3.4).
        $fixs = $this->product($group, 'FIXS-SET2300 P', 'FIXS złoty 2,3m', 'złoty', '82.96');
        $knob = $this->product($group, 'KHJ24PBC', 'GAŁKA DO DRZWI 35 x 35', 'złoty', '72.08');

        $this->priced([$fixs, $knob]);

        // Zapisana konfiguracja klienta — jedyny zestaw, ktorego sklad
        // jest udokumentowany. Nie pokazuje sie w bibliotece szablonow
        // i o to chodzi: komplet zlozony dla jednej osoby nalezy do jej
        // historii, a nie do kuratorowanej listy.
        $this->set('23) Marta Grabowska', FittingSet::KIND_CONFIGURATION, [
            [$fixs, 1],
            [$knob, 1],
        ]);

        // Nazwa szablonu jest udokumentowana, jego sklad juz nie —
        // zrzut pokazuje sama liste nazw po lewej. Sklad ponizej jest
        // zaslepka deweloperska, zeby „Wybierz zestaw" mialo z czego
        // wybierac; prawdziwy przyjdzie ze zrzutem starej bazy.
        $this->set('1) System przesuwny terno clear', FittingSet::KIND_TEMPLATE, [
            [$fixs, 1],
            [$knob, 2],
        ]);
    }

    private function group(): ProductGroup
    {
        /** @var ProductGroup */
        return ProductGroup::query()->firstOrCreate(
            ['section' => Section::FITTINGS->value, 'name' => 'CDA - ETNA'],
            [
                'section' => Section::FITTINGS->value,
                'name' => 'CDA - ETNA',
                // Producent i seria rozbite na osobne pola — M-06
                // rozstrzygniete w modelu, w starym systemie to jedno
                // pole tekstowe.
                'manufacturer' => 'CDA',
                'series' => 'ETNA',
                'position' => 10,
            ],
        );
    }

    /**
     * Nazwa odtworzona z kodu.
     *
     * Zrzut slownika mapuje na nazwe tylko dwie pozycje („Unoszony LEWY
     * 90 stopni", „Unoszony odwr. PRAWY 90 stopni mocowanie boczne"),
     * ale wzor kodu jest z nich czytelny: TGHU + kat + LH/RH, a `-OS`
     * znaczy wersje odwrocona z mocowaniem bocznym.
     */
    private function name(string $code): string
    {
        preg_match('/TGHU(\d+)(-OS)?(LH|RH)/', $code, $parts);

        $angle = $parts[1] ?? '90';
        $side = ($parts[3] ?? 'LH') === 'LH' ? 'LEWY' : 'PRAWY';
        $reversed = ($parts[2] ?? '') !== '';

        return $reversed
            ? sprintf('Unoszony odwr. %s %s stopni mocowanie boczne', $side, $angle)
            : sprintf('Unoszony %s %s stopni', $side, $angle);
    }

    /**
     * Wykończenie z końcówki kodu.
     *
     * Udokumentowane jest jedno: `PBC` to „złoty" (`40-magazyn.md`
     * §3.2). Reszty nie rozwijamy na polskie nazwy, bo byłoby to
     * zgadywanie — zostaje sam skrót, tak jak stoi w katalogu.
     */
    private function finish(string $code): string
    {
        $suffix = substr($code, (int) strrpos($code, ' ') + 1);

        return $suffix === 'PBC' ? 'złoty' : $suffix;
    }

    private function product(
        ProductGroup $group,
        string $code,
        string $name,
        string $finish,
        ?string $purchaseNet,
    ): Product {
        /** @var Product $product */
        $product = Product::query()->firstOrCreate(
            ['section' => Section::FITTINGS->value, 'code' => $code],
            [
                'product_group_id' => $group->id,
                'section' => Section::FITTINGS->value,
                'code' => $code,
                'manufacturer_code' => $code,
                'name' => $name,
                'unit' => Unit::PIECE->value,
                'vat_rate' => 23,
            ],
        );

        ProductFitting::query()->firstOrCreate(
            ['product_id' => $product->id],
            ['product_id' => $product->id, 'finish' => $finish],
        );

        if ($purchaseNet !== null) {
            PurchasePrice::query()->firstOrCreate(
                ['product_id' => $product->id, 'valid_from' => self::referenceDate()],
                [
                    'product_id' => $product->id,
                    'net_price' => $purchaseNet,
                    'source' => PurchasePriceSource::MANUAL->value,
                    'valid_from' => self::referenceDate(),
                ],
            );
        }

        return $product;
    }

    /**
     * Współczynnik 1,0 jest zaślepką, tak samo jak przy usługach:
     * kolumna w słowniku to cena **zakupu**, a marży na okuciach nie
     * dostaliśmy.
     *
     * @param list<Product> $products
     */
    private function priced(array $products): void
    {
        /** @var PriceSection|null $section */
        $section = PriceSection::query()
            ->where('section', Section::FITTINGS->value)
            ->where('is_default', true)
            ->first();

        if ($section === null) {
            return;
        }

        $cells = [];

        foreach ($products as $product) {
            $cells[] = [
                'product_id' => (int) $product->getKey(),
                'price_section_id' => (int) $section->getKey(),
                'coefficient' => '1.0',
            ];
        }

        (new PriceListService())->update($cells);
    }

    /**
     * @param list<array{0: Product, 1: int}> $items
     */
    private function set(string $name, string $kind, array $items): void
    {
        /** @var FittingSet $set */
        $set = FittingSet::query()->firstOrCreate(
            ['name' => $name],
            ['name' => $name, 'kind' => $kind],
        );

        $position = 0;

        foreach ($items as [$product, $quantity]) {
            FittingSetItem::query()->firstOrCreate(
                ['fitting_set_id' => $set->id, 'product_id' => $product->id],
                [
                    'fitting_set_id' => $set->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'position' => $position += 10,
                ],
            );
        }
    }
}
