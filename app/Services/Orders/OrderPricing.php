<?php

declare(strict_types=1);

namespace App\Services\Orders;

use Carbon\Carbon;
use App\Enum\Section;
use App\Models\Order;
use App\Models\Product;
use App\Models\Process;
use App\DTO\Orders\PanePrice;
use App\Models\ProductService;
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
 * **Cena procesu zależy od grubości szkła.** Szlif ośmiomilimetrowej
 * szyby kosztuje więcej niż czteromilimetrowej, więc w cenniku proces
 * występuje jako kilka pozycji różniących się `glass_thickness_mm`.
 * Pozycja bez grubości obowiązuje dla każdej — i przegrywa z pozycją
 * dopasowaną, jeśli taka istnieje.
 */
final readonly class OrderPricing
{
    public function __construct(
        private PriceResolver $prices = new PriceResolver(),
        private PaneCalculator $calculator = new PaneCalculator(),
    ) {
    }

    /**
     * @param list<int> $processIds
     */
    public function pane(
        Order $order,
        Product $product,
        PaneSpecification $pane,
        array $processIds = [],
        ?Carbon $date = null,
    ): PanePrice {
        $date ??= Carbon::today();
        $thickness = $product->glass?->thickness_mm;
        $weight = $product->glass?->weightOfPane($pane->widthMm, $pane->heightMm, $pane->quantity) ?? 0.0;

        $resolved = $this->prices->forContractor($product, $order->contractor, $date);

        if (!$resolved->isAvailable()) {
            return new PanePrice(
                glassNet: '0.00',
                netPricePerSquareMeter: null,
                processes: $this->processes($processIds, $thickness, $order, $date, $pane, false),
                steps: [],
                billableSquareMeters: round($pane->squareMeters(), 4),
                runningMeters: round($pane->runningMeters(), 2),
                weightKg: $weight,
                unavailableReason: $resolved->unavailableReason?->value,
            );
        }

        $processes = $this->processes($processIds, $thickness, $order, $date, $pane, true);
        $parameters = PricingParameters::effective($date);

        $quote = $this->calculator->calculate(
            $pane,
            (string) $resolved->netPrice,
            $parameters,
            array_map(
                static fn(array $process): array => [
                    'label' => $process['label'],
                    'net_price_per_running_meter' => $process['unit_net_price'],
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
     * @param list<int> $processIds
     * @return list<array{
     *     process_id: int,
     *     code: string,
     *     label: string,
     *     unit_net_price: string,
     *     amount: string,
     *     unavailable: string|null
     * }>
     */
    private function processes(
        array $processIds,
        ?float $thickness,
        Order $order,
        Carbon $date,
        PaneSpecification $pane,
        bool $withAmounts,
    ): array {
        if ($processIds === []) {
            return [];
        }

        /** @var iterable<Process> $catalogue */
        $catalogue = Process::query()
            ->whereIn('id', $processIds)
            ->where('is_active', true)
            ->orderBy('default_order')
            ->get();

        $rows = [];

        foreach ($catalogue as $process) {
            $product = $this->processProduct($process, $thickness);
            $price = $product === null
                ? null
                : $this->prices->forContractor($product, $order->contractor, $date);

            $unit = $price !== null && $price->isAvailable() ? (string) $price->netPrice : null;

            $rows[] = [
                'process_id' => (int) $process->getKey(),
                'code' => $process->code,
                'label' => $process->name,
                'unit_net_price' => $unit ?? '0.00',
                'amount' => $unit === null || !$withAmounts
                    ? '0.00'
                    : $this->calculator->processAmount($pane, $unit),
                // Proces bez pozycji w cenniku nie kosztuje zera —
                // kosztuje „nie wiadomo ile", i to trzeba powiedziec.
                'unavailable' => $unit === null
                    ? ($product === null ? 'no_price_list_item' : $price?->unavailableReason?->value)
                    : null,
            ];
        }

        return $rows;
    }

    /**
     * Pozycja cennika procesu dopasowana do grubości szkła; pozycja bez
     * grubości obowiązuje dla każdej i jest uzywana dopiero wtedy, gdy
     * dopasowanej nie ma.
     */
    private function processProduct(Process $process, ?float $thickness): ?Product
    {
        /** @var iterable<ProductService> $services */
        $services = ProductService::query()
            ->with('product')
            ->where('process_id', (int) $process->getKey())
            ->get();

        $fallback = null;

        foreach ($services as $service) {
            $product = $service->product;

            if (!$product instanceof Product
                || $product->section !== Section::SERVICES
                || !$product->is_active) {
                continue;
            }

            if ($thickness !== null && $service->glass_thickness_mm === $thickness) {
                return $product;
            }

            if ($service->glass_thickness_mm === null && $fallback === null) {
                $fallback = $product;
            }
        }

        return $fallback;
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
