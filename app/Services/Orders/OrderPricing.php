<?php

declare(strict_types=1);

namespace App\Services\Orders;

use Carbon\Carbon;
use App\Enum\Unit;
use App\Enum\Section;
use App\Models\Order;
use App\Models\Product;
use App\Models\Process;
use App\DTO\Orders\PanePrice;
use App\Models\ProductService;
use App\DTO\Pricing\ResolvedPrice;
use App\DTO\Pricing\PaneSpecification;
use App\DTO\Pricing\PricingParameters;
use App\Services\Pricing\PriceResolver;
use App\Services\Pricing\PaneCalculator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Wycena formatki w kontekście zlecenia.
 *
 * Spina trzy rzeczy, które osobno już istnieją: cenę materiału dla tego
 * kontrahenta (`PriceResolver`, poziomy 1–3), wzór wyceny formatki
 * (`PaneCalculator`) i cennik procesów. Sama nie liczy niczego — od
 * liczenia jest kalkulator, tutaj jest tylko decyzja, czym go nakarmić.
 *
 * **Grubość szkła zawęża listę pozycji procesu, ale jej nie
 * rozstrzyga.** Cięcie ma dla każdej grubości jeden wiersz, więc tam
 * dopasowanie jest jednoznaczne. Fazowanie ma dla ośmiomilimetrowej
 * szyby osiem wierszy — fazy od 5 do 40 mm, od 5 do 22,50 zł — a CNC
 * cztery wiersze w ogóle od grubości niezależne. Wybór między nimi
 * należy do człowieka.
 *
 * Dlatego automat wybiera **tylko wtedy, gdy kandydat jest dokładnie
 * jeden**. Przy kilku kandydatach pozycja czeka na wybór i jest
 * oznaczona jako niewyceniona. Branie pierwszego z brzegu wstawiłoby
 * cichaczem fazę 5 mm za 5 zł tam, gdzie klient zamówił 30 mm za 17 —
 * i nikt by tego nie zauważył, bo kwota by się pojawiła.
 */
final readonly class OrderPricing
{
    public function __construct(
        private PriceResolver $prices = new PriceResolver(),
        private PaneCalculator $calculator = new PaneCalculator(),
    ) {
    }

    /**
     * @param list<array<string, mixed>> $selections wybrane procesy razem
     *        z pozycją cennikową, dniami, ceną i komentarzem
     */
    public function pane(
        Order $order,
        Product $product,
        PaneSpecification $pane,
        array $selections = [],
        ?Carbon $date = null,
    ): PanePrice {
        $date ??= Carbon::today();
        $thickness = $product->glass?->thickness_mm;
        $weight = $product->glass?->weightOfPane($pane->widthMm, $pane->heightMm, $pane->quantity) ?? 0.0;

        $resolved = $this->prices->forContractor($product, $order->contractor, $date);

        // Brak ceny materialu nie unieważnia procesów. Szlif ma własną
        // pozycję w cenniku Usług i kosztuje tyle samo niezależnie od
        // tego, czy ktoś wypełnił macierz dla tej grubości szkła.
        // Zerowanie procesów robiło z formatki za 32,50 formatkę za 0,00
        // i chowało jedyną kwotę, która była znana.
        if (!$resolved->isAvailable()) {
            return new PanePrice(
                glassNet: '0.00',
                netPricePerSquareMeter: null,
                processes: $this->processes($selections, $thickness, $order, $date, $pane),
                steps: [],
                billableSquareMeters: round($pane->squareMeters(), 4),
                runningMeters: round($pane->runningMeters(), 2),
                weightKg: $weight,
                unavailableReason: $resolved->unavailableReason?->value,
            );
        }

        $processes = $this->processes($selections, $thickness, $order, $date, $pane);
        $parameters = PricingParameters::effective($date);

        $quote = $this->calculator->calculate(
            $pane,
            (string) $resolved->netPrice,
            $parameters,
            array_map(
                static fn(array $process): array => [
                    'label' => $process['label'],
                    'unit_net_price' => $process['unit_net_price'],
                    'unit' => $process['unit'],
                ],
                $processes,
            ),
        );

        // Kalkulator zwraca kwote lacznie z procesami, bo tak liczy sie
        // cene formatki. Na zleceniu proces jest osobnym wierszem, wiec
        // materialowi zostaje reszta — sama odejmuje sie co do grosza,
        // bo kazdy skladnik jest zaokraglony do dwoch miejsc.
        $glass = (float) $quote->net;

        foreach ($processes as $process) {
            $glass -= (float) $process['amount'];
        }

        return new PanePrice(
            glassNet: number_format(round($glass, 2), 2, '.', ''),
            netPricePerSquareMeter: (string) $resolved->netPrice,
            processes: $processes,
            steps: array_merge(
                array_map(
                    static fn($step): array => $step->toArray(),
                    $resolved->steps,
                ),
                array_map(
                    static fn($step): array => $step->toArray(),
                    $quote->steps,
                ),
            ),
            billableSquareMeters: $quote->billableSquareMeters,
            runningMeters: $quote->runningMeters,
            weightKg: $weight,
        );
    }

    /**
     * @param list<array<string, mixed>> $selections
     * @return list<array{
     *     process_id: int,
     *     code: string,
     *     label: string,
     *     product_id: int|null,
     *     unit: Unit,
     *     unit_label: string,
     *     units: float,
     *     parameter: string|null,
     *     days: int|null,
     *     comment: string|null,
     *     unit_net_price: string,
     *     amount: string,
     *     unavailable: string|null
     * }>
     */
    private function processes(
        array $selections,
        ?float $thickness,
        Order $order,
        Carbon $date,
        PaneSpecification $pane,
    ): array {
        if ($selections === []) {
            return [];
        }

        $ids = [];

        foreach ($selections as $selection) {
            $ids[] = (int) ($selection['process_id'] ?? 0);
        }

        /** @var array<int, Process> $catalogue */
        $catalogue = Process::query()
            ->whereIn('id', array_values(array_filter($ids)))
            ->where('is_active', true)
            ->orderBy('default_order')
            ->get()
            ->keyBy(static fn(Process $process): int => (int) $process->getKey())
            ->all();

        $rows = [];

        foreach ($selections as $selection) {
            $process = $catalogue[(int) ($selection['process_id'] ?? 0)] ?? null;

            if (!$process instanceof Process) {
                continue;
            }

            $candidates = $this->candidates($process, $thickness);
            $product = $this->chosen($candidates, $selection['product_id'] ?? null);
            $price = $product === null
                ? null
                : $this->prices->forContractor($product, $order->contractor, $date);

            $catalogueRate = $price !== null && $price->isAvailable() ? (string) $price->netPrice : null;
            // Cena wpisana recznie wygrywa z cennikiem, ale tylko wtedy,
            // gdy ktos ja faktycznie wpisal. Puste pole to nie zero.
            $manual = $this->amount($selection['unit_net_price'] ?? null);
            $rate = $manual ?? $catalogueRate;

            $unit = $product === null ? Unit::RUNNING_METER : $product->unit;
            $units = $this->calculator->units($pane, $unit);

            $rows[] = [
                'process_id' => (int) $process->getKey(),
                'code' => $process->code,
                'label' => $process->name,
                'product_id' => $product === null ? null : (int) $product->getKey(),
                // Jednostka procesu, ile jej niesie ta formatka i gotowa
                // formula — zeby kwota nie byla liczba bez pochodzenia.
                'unit' => $unit,
                'unit_label' => $this->calculator->unitLabel($unit),
                'units' => round($units, 3),
                // Parametr to nazwa wybranej pozycji — „Faza 15mm",
                // „RAL 9005". Ta sama wartosc jedzie potem na karte
                // operatora na hali, wiec nie wymyslamy jej osobno.
                'parameter' => $product?->name,
                'days' => $this->days($selection['days'] ?? null, $process),
                'comment' => $this->text($selection['comment'] ?? null),
                'unit_net_price' => $rate ?? '0.00',
                'amount' => $rate === null
                    ? '0.00'
                    : $this->calculator->processAmount($pane, $rate, $unit),
                // Proces bez wybranej pozycji nie kosztuje zera — kosztuje
                // „nie wiadomo ile", i to trzeba powiedziec.
                'unavailable' => $rate !== null ? null : $this->why($candidates, $product, $price),
            ];
        }

        return $rows;
    }

    /**
     * Pozycje cennika procesu dopasowane do grubości szkła. Pozycja bez
     * grubości obowiązuje dla każdej i wchodzi dopiero wtedy, gdy nic
     * dopasowanego nie ma.
     *
     * @return list<Product>
     */
    public function candidates(Process $process, ?float $thickness): array
    {
        /** @var iterable<ProductService> $services */
        $services = ProductService::query()
            ->with('product')
            ->where('process_id', (int) $process->getKey())
            ->get();

        $matched = [];
        $universal = [];

        foreach ($services as $service) {
            $product = $service->product;

            if (!$product instanceof Product
                || $product->section !== Section::SERVICES
                || !$product->is_active) {
                continue;
            }

            if ($thickness !== null && $service->glass_thickness_mm === $thickness) {
                $matched[] = $product;

                continue;
            }

            if ($service->glass_thickness_mm === null) {
                $universal[] = $product;
            }
        }

        return $matched !== [] ? $matched : $universal;
    }

    /**
     * Wybrana pozycja. Automat decyduje **tylko przy jednym kandydacie** —
     * przy kilku wybór należy do człowieka, a wskazanie spoza listy jest
     * odrzucane, żeby faza nie trafiła do zlecenia jako lakier.
     *
     * @param list<Product> $candidates
     */
    private function chosen(array $candidates, mixed $productId): ?Product
    {
        $id = is_numeric($productId) ? (int) $productId : null;

        if ($id !== null) {
            foreach ($candidates as $product) {
                if ((int) $product->getKey() === $id) {
                    return $product;
                }
            }

            return null;
        }

        return count($candidates) === 1 ? $candidates[0] : null;
    }

    /**
     * Dlaczego etap jest niewyceniony. Zawsze jest jakiś powód — brak
     * ceny bez powodu to zero w przebraniu.
     *
     * @param list<Product> $candidates
     */
    private function why(array $candidates, ?Product $product, ?ResolvedPrice $price): string
    {
        if ($product !== null) {
            $reason = $price?->unavailableReason;

            return $reason === null ? 'no_price_list_item' : $reason->value;
        }

        // Zaden kandydat to brak pozycji w cenniku; kilku — wybor,
        // ktorego nikt jeszcze nie dokonal.
        return $candidates === [] ? 'no_price_list_item' : 'needs_choice';
    }

    /**
     * Dni ze słownika procesów, chyba że ktoś wpisał własne. Zero jest
     * prawidłową odpowiedzią — etap tego samego dnia — więc nie traktujemy
     * go jak braku.
     */
    private function days(mixed $value, Process $process): ?int
    {
        if (is_numeric($value)) {
            return max(0, (int) $value);
        }

        return $process->duration_days;
    }

    private function amount(mixed $value): ?string
    {
        if (!is_numeric($value)) {
            return null;
        }

        return number_format(round((float) $value, 2), 2, '.', '');
    }

    private function text(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $text = trim($value);

        return $text === '' ? null : $text;
    }

    /**
     * Produkty, z których da się zbudować formatkę — szkło w cenniku.
     *
     * @return Builder<Product>
     */
    public static function glassProducts(): Builder
    {
        return Product::query()
            ->with('glass')
            ->where('section', Section::GLASS->value)
            ->where('is_active', true)
            ->orderBy('name');
    }
}
