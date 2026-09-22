<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Enum\Section;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderList;
use App\Models\OrderDiscount;
use App\DTO\Orders\OrderTotals;
use App\DTO\Orders\InvestmentVat;

/**
 * Wartość zlecenia: sumy pozycji, rabat i VAT.
 *
 * Rabat to **czwarty i ostatni poziom ceny** (`10-zlecenia.md` §5.2):
 * katalog → sekcja cenowa kontrahenta → cena indywidualna → rabat na
 * zleceniu. Trzy pierwsze są zamrożone w pozycji w chwili wyceny;
 * czwarty nie, bo dotyczy całego zlecenia i zmienia się w negocjacjach.
 *
 * **Rabat nie zmienia kwot pozycji.** Gdyby zmieniał, każda zmiana
 * rabatu wymagałaby przeliczenia wszystkich formatek i kasowała ślad
 * wyceny, który jest jedyną odpowiedzią na pytanie „skąd ta cena".
 * Dlatego pozycja trzyma kwotę sprzed rabatu, a rabat jest osobną
 * pozycją podsumowania — tak samo jak na ofercie dla klienta.
 *
 * Rabaty per sekcja **nie sumują się procentowo**: 10 % na szkle i 5 %
 * na usługach to nie jest 15 % na zleceniu. Stąd rabat łączny wychodzi
 * wyłącznie kwotowo.
 *
 * ## VAT
 *
 * Stawka siedzi na liście, nie na zleceniu: montaż w budynku
 * mieszkalnym idzie na 8 %, a szkło w tym samym zleceniu na 23 %.
 * `order_lists.vat_rate = null` znaczy „jak w typie faktury", nie
 * „zero". Gdy i typu faktury nie ma, kwota trafia do koszyka
 * „stawka nieznana" i brutto całego zlecenia jest `null` — bo sumy
 * z dziurą podać się nie da.
 *
 * Rabat jest liczony na sekcję, a stawka na listę, więc rabat sekcji
 * trzeba **rozdzielić na listy** proporcjonalnie do ich udziału w tej
 * sekcji. Reszta z zaokrągleń ląduje na ostatniej liście danej sekcji,
 * żeby suma netto list zgadzała się z netto zlecenia co do grosza.
 */
final readonly class OrderValue
{
    public function __construct(
        private VatSplit $split = new VatSplit(),
    ) {
    }

    public function totals(Order $order): OrderTotals
    {
        $percents = $this->percents($order);
        $listBases = $this->listBases($order);

        [$sections, $base, $discount] = $this->sections($listBases, $percents);

        $net = round($base - $discount, 2);
        $listNets = $this->listNets($listBases, $percents);

        return $this->withVat(
            $order,
            $listNets,
            base: $base,
            discount: $discount,
            net: $net,
            sections: $sections,
        );
    }

    /** Sama kwota netto po rabacie — dla list i warunków przejść. */
    public function net(Order $order): string
    {
        return $this->totals($order)->net;
    }

    /** Podział stawki obniżonej albo `null`, gdy zlecenie nie ma inwestycji. */
    public function investmentVat(Order $order): ?InvestmentVat
    {
        return $this->split->for($order);
    }

    /**
     * Kwota każdej listy — także wyłączonej z sumy zlecenia.
     *
     * Potrzebne ofercie, bo oferta wariantowa pokazuje alternatywy obok
     * składników: klient ma zobaczyć, ile kosztuje szkło 8 mm zamiast
     * 6 mm, mimo że do kwoty zlecenia wchodzi tylko jedno z nich.
     *
     * **Dwie drogi liczenia i to jest świadome.** Listy wliczone dostają
     * kwotę z rozdzielenia rabatu sekcji, razem z resztą z zaokrągleń,
     * więc ich suma zgadza się z netto zlecenia co do grosza. Lista
     * wyłączona nie należy do żadnej sekcji w tym sensie — jej rabatu
     * nie ma z czego rozdzielać — więc dostaje po prostu procent swojej
     * sekcji. To jest cena, którą klient zapłaciłby po wyborze tego
     * wariantu, a nie udział w kwocie, której wariant nie tworzy.
     *
     * @return array<int, array{net: float, included: bool}>
     */
    public function perList(Order $order): array
    {
        $percents = $this->percents($order);
        $included = $this->listNets($this->listBases($order), $percents);

        $rows = [];

        /** @var OrderList $list */
        foreach ($order->lists as $list) {
            $id = (int) $list->getKey();

            if ($list->is_included) {
                $rows[$id] = ['net' => $included[$id] ?? 0.0, 'included' => true];

                continue;
            }

            $net = 0.0;

            /** @var OrderItem $item */
            foreach ($list->items as $item) {
                $amount = (float) $item->amount;

                foreach ($item->processes as $process) {
                    $amount += (float) $process->amount;
                }

                $percent = $percents[$item->section->value] ?? 0.0;
                $net += $amount - round($amount * $percent / 100, 2);
            }

            $rows[$id] = ['net' => round($net, 2), 'included' => false];
        }

        return $rows;
    }

    /**
     * Sumy per stawka. Zaokrąglamy raz na stawkę, a nie na listę:
     * tak liczy się VAT na fakturze i tak zgadza się z księgowością.
     *
     * @param array<int, float> $listNets
     * @param list<array{section: string, base: string, percent: string, discount: string, net: string}> $sections
     */
    private function withVat(
        Order $order,
        array $listNets,
        float $base,
        float $discount,
        float $net,
        array $sections,
    ): OrderTotals {
        $split = $this->split->for($order);
        $default = $order->invoiceType?->vat_rate;

        /** @var array<int, float> $buckets */
        $buckets = [];
        $unknown = 0.0;
        $reason = null;

        /** @var OrderList $list */
        foreach ($order->lists as $list) {
            if (!$list->is_included) {
                continue;
            }

            $listNet = $listNets[(int) $list->getKey()] ?? 0.0;

            if ($listNet === 0.0) {
                continue;
            }

            $rate = $list->vat_rate ?? $default;

            if ($rate === null) {
                $unknown += $listNet;
                $reason ??= 'Zlecenie nie ma typu faktury, a lista nie ma własnej stawki.';

                continue;
            }

            // Podzial dotyczy wylacznie list ze stawka obnizona. Szklo
            // na 23 % nie ma czego dzielic — limit powierzchni odnosi
            // sie do preferencji, nie do calej sprzedazy.
            if ($split !== null && $rate === $split->reducedRate) {
                if ($split->share === null) {
                    $unknown += $listNet;
                    $reason ??= $split->reason;

                    continue;
                }

                $reducedNet = round($listNet * $split->share, 2);
                $buckets[$split->reducedRate] = ($buckets[$split->reducedRate] ?? 0.0) + $reducedNet;

                // Reszta, a nie druga proporcja: inaczej suma dwoch
                // zaokraglen rozjezdza sie z netto listy o grosz.
                $standardNet = round($listNet - $reducedNet, 2);

                if ($standardNet !== 0.0) {
                    $buckets[$split->standardRate] = ($buckets[$split->standardRate] ?? 0.0) + $standardNet;
                }

                continue;
            }

            $buckets[$rate] = ($buckets[$rate] ?? 0.0) + $listNet;
        }

        krsort($buckets);

        $lines = [];
        $vat = 0.0;

        foreach ($buckets as $rate => $bucketNet) {
            $bucketNet = round($bucketNet, 2);
            $bucketVat = round($bucketNet * $rate / 100, 2);
            $vat += $bucketVat;

            $lines[] = [
                'rate' => (int) $rate,
                'net' => $this->money($bucketNet),
                'vat' => $this->money($bucketVat),
                'gross' => $this->money($bucketNet + $bucketVat),
            ];
        }

        $unknown = round($unknown, 2);

        // Puste zlecenie z typem faktury ma stawke, tylko nie ma od
        // czego jej policzyc. Zero to wtedy prawdziwa odpowiedz, a nie
        // brak danych — inaczej nowo zalozone zlecenie mowiloby, ze nie
        // zna swojego brutto.
        $isKnown = $unknown === 0.0 && ($lines !== [] || $default !== null);

        // Bez `?? null` przy `$lines[0]`: gałąź `$lines === []` stoi
        // wyżej, więc tutaj lista jest już niepusta. Domyślka broniłaby
        // przed przypadkiem, którego typ nie dopuszcza.
        $single = match (true) {
            !$isKnown => null,
            $lines === [] => $default,
            count($lines) === 1 => $lines[0]['rate'],
            default => null,
        };

        return new OrderTotals(
            base: $this->money($base),
            discount: $this->money($discount),
            net: $this->money($net),
            excludedNet: $this->money($this->sum($order, false)),
            // Jedna stawka to jedna stawka. Przy kilku `null` nie znaczy
            // „nie wiemy" — od tego jest `unknownNet`.
            vatRate: $single,
            vat: $isKnown ? $this->money($vat) : null,
            gross: $isKnown ? $this->money($net + $vat) : null,
            unknownNet: $this->money($unknown),
            unknownReason: $unknown === 0.0 ? null : $reason,
            mixedVat: count($lines) > 1,
            vatLines: $lines,
            sections: $sections,
        );
    }

    /**
     * Wiersze podsumowania per sekcja plus podstawa i rabat łączny.
     *
     * @param array<int, array<string, float>> $listBases
     * @param array<string, float> $percents
     * @return array{0: list<array{section: string, base: string, percent: string, discount: string, net: string}>, 1: float, 2: float}
     */
    private function sections(array $listBases, array $percents): array
    {
        $rows = [];
        $base = 0.0;
        $discount = 0.0;

        foreach (Section::cases() as $section) {
            $sectionBase = $this->sectionBase($listBases, $section->value);
            $percent = $percents[$section->value] ?? 0.0;

            // Sekcja bez pozycji i bez rabatu nie zasluguje na wiersz
            // w podsumowaniu — stary system pokazywal wszystkie cztery,
            // w tym trzy wypelnione zerami.
            if ($sectionBase === 0.0 && $percent === 0.0) {
                continue;
            }

            $sectionDiscount = round($sectionBase * $percent / 100, 2);

            $base += $sectionBase;
            $discount += $sectionDiscount;

            $rows[] = [
                'section' => $section->value,
                'base' => $this->money($sectionBase),
                'percent' => $this->percent($percent),
                'discount' => $this->money($sectionDiscount),
                'net' => $this->money($sectionBase - $sectionDiscount),
            ];
        }

        return [$rows, $base, $discount];
    }

    /**
     * Netto każdej wliczonej listy po rozdzieleniu rabatów sekcji.
     *
     * @param array<int, array<string, float>> $listBases
     * @param array<string, float> $percents
     * @return array<int, float>
     */
    private function listNets(array $listBases, array $percents): array
    {
        $nets = [];

        foreach ($listBases as $listId => $bySection) {
            $nets[$listId] = round(array_sum($bySection), 2);
        }

        foreach (Section::cases() as $section) {
            $key = $section->value;
            $sectionBase = $this->sectionBase($listBases, $key);
            $percent = $percents[$key] ?? 0.0;

            if ($sectionBase === 0.0 || $percent === 0.0) {
                continue;
            }

            $sectionDiscount = round($sectionBase * $percent / 100, 2);

            /** @var list<int> $ids */
            $ids = [];

            foreach ($listBases as $listId => $bySection) {
                if (($bySection[$key] ?? 0.0) !== 0.0) {
                    $ids[] = $listId;
                }
            }

            $allocated = 0.0;
            $last = count($ids) - 1;

            foreach ($ids as $index => $listId) {
                // Ostatnia lista sekcji dostaje reszte, a nie swoja
                // proporcje: suma groszy musi sie zgadzac z rabatem
                // sekcji, bo inaczej netto zlecenia i suma netto list
                // to dwie rozne kwoty.
                $part = $index === $last
                    ? round($sectionDiscount - $allocated, 2)
                    : round($sectionDiscount * $listBases[$listId][$key] / $sectionBase, 2);

                $allocated += $part;
                $nets[$listId] = round($nets[$listId] - $part, 2);
            }
        }

        return $nets;
    }

    /**
     * Podstawa rabatu w rozbiciu na listę i sekcję asortymentu,
     * z list wliczonych do zlecenia.
     *
     * @return array<int, array<string, float>>
     */
    private function listBases(Order $order): array
    {
        $bases = [];

        /** @var OrderList $list */
        foreach ($order->lists as $list) {
            if (!$list->is_included) {
                continue;
            }

            $listId = (int) $list->getKey();
            $bases[$listId] ??= [];

            /** @var OrderItem $item */
            foreach ($list->items as $item) {
                $key = $item->section->value;
                $amount = (float) $item->amount;

                foreach ($item->processes as $process) {
                    $amount += (float) $process->amount;
                }

                $bases[$listId][$key] = ($bases[$listId][$key] ?? 0.0) + $amount;
            }
        }

        return $bases;
    }

    /**
     * @param array<int, array<string, float>> $listBases
     */
    private function sectionBase(array $listBases, string $section): float
    {
        $total = 0.0;

        foreach ($listBases as $bySection) {
            $total += $bySection[$section] ?? 0.0;
        }

        return $total;
    }

    /**
     * @return array<string, float>
     */
    private function percents(Order $order): array
    {
        $percents = [];

        /** @var OrderDiscount $discount */
        foreach ($order->discounts as $discount) {
            $percents[$discount->section->value] = (float) $discount->percent;
        }

        return $percents;
    }

    private function sum(Order $order, bool $included): float
    {
        $total = 0.0;

        /** @var OrderList $list */
        foreach ($order->lists as $list) {
            if ((bool) $list->is_included !== $included) {
                continue;
            }

            /** @var OrderItem $item */
            foreach ($list->items as $item) {
                $total += (float) $item->amount;

                foreach ($item->processes as $process) {
                    $total += (float) $process->amount;
                }
            }
        }

        return $total;
    }

    private function money(float $value): string
    {
        return number_format(round($value, 2), 2, '.', '');
    }

    private function percent(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
