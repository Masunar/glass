<?php

declare(strict_types=1);

namespace App\Services\Offers;

use App\Models\Offer;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Enum\OfferPriceDisplay;

/**
 * Wydruk oferty.
 *
 * Buduje PDF **wyłącznie z migawki**, nigdy ze zlecenia. Dzięki temu
 * wydruk sprzed miesiąca wygląda dziś tak samo — i dlatego pliku nie
 * przechowujemy: generowanie z zamrożonej treści jest powtarzalne,
 * a druga kopia tej samej prawdy to jedno miejsce więcej, w którym
 * mogłyby się rozjechać.
 *
 * Szablon jest osobny od CSS aplikacji, bo dompdf renderuje HTML
 * w standardzie sprzed flexboxa: układ na tabelach, `DejaVu Sans`
 * jawnie (domyślna rodzina gubi polskie znaki).
 */
final readonly class OfferDocument
{
    /**
     * Gotowy plik PDF jako ciąg bajtów.
     *
     * `$preview` nie jest przełącznikiem wyglądu, tylko zabezpieczeniem:
     * PDF podglądu bez oznaczenia, który trafi do klienta, jest
     * dokumentem bez śladu w systemie — i nie da się potem odpowiedzieć
     * na pytanie „co mu wysłaliśmy".
     */
    public function render(Offer $offer, bool $preview = false): string
    {
        return Pdf::loadView('offers.document', $this->data($offer, $preview))
            ->setPaper('a4')
            ->output();
    }

    /** Nazwa pliku podglądu — bez numeru, bo numeru jeszcze nie ma. */
    public function previewFileName(Offer $offer): string
    {
        /** @var array<string, mixed> $snapshot */
        $snapshot = $offer->snapshot;

        /** @var array<string, mixed> $order */
        $order = $snapshot['order'] ?? [];

        return 'podglad-oferty-' . ($order['number'] ?? 'zlecenie') . '.pdf';
    }

    /**
     * Nazwa pliku dla człowieka: `oferta-24046-1.pdf`.
     *
     * Ukośnik z numeru oferty nie przejdzie przez system plików,
     * a pod nim i tak zwykle ląduje katalog.
     */
    public function fileName(Offer $offer): string
    {
        return 'oferta-' . str_replace('/', '-', $offer->number()) . '.pdf';
    }

    /**
     * @return array<string, mixed>
     */
    private function data(Offer $offer, bool $preview = false): array
    {
        /** @var array<string, mixed> $snapshot */
        $snapshot = $offer->snapshot;

        /** @var array<string, mixed> $totals */
        $totals = $snapshot['totals'] ?? [];

        /** @var list<array<string, mixed>> $lists */
        $lists = $snapshot['lists'] ?? [];

        $isGross = $offer->price_display === OfferPriceDisplay::GROSS;

        return [
            'isPreview' => $preview,
            // Bez `number()` przy podgladzie: oferta nie jest zapisana,
            // wiec numeru nie ma — i o to chodzi.
            'number' => $preview ? null : $offer->number(),
            'issuedOn' => $snapshot['issued_on'] ?? $offer->issued_at->toDateString(),
            'validUntil' => $offer->valid_until?->toDateString(),
            // Klucze dopelniane na wejsciu, a nie sprawdzane w szablonie:
            // migawka starszej oferty moze ich nie miec, a wydruk ma sie
            // wtedy zlozyc z dziura, a nie wywalic.
            'seller' => $this->fill($snapshot['seller'] ?? [], [
                'name', 'address', 'tax_id', 'phone', 'email', 'bank_account',
            ]),
            'buyer' => $this->fill($snapshot['buyer'] ?? [], [
                'name', 'tax_id', 'address', 'contact',
            ]),
            'order' => $this->fill($snapshot['order'] ?? [], [
                'number', 'deadline', 'offer_comment',
            ]),
            'lists' => $lists,
            'sum' => $snapshot['sum'] ?? ['is_shown' => false],
            // Rozbicie na stawki tylko przy ofercie brutto. Na ofercie
            // netto VAT jest informacja, ktorej klient nie potrzebuje
            // do podjecia decyzji, a ktora zasmieca podsumowanie.
            'vatLines' => $isGross ? ($totals['vat_lines'] ?? []) : [],
            'isGross' => $isGross,
            'hasAlternatives' => $this->hasAlternatives($lists),
            'comment' => $offer->comment,
            'texts' => array_values($snapshot['texts'] ?? []),
            'money' => static fn(mixed $value): string => number_format(
                (float) $value,
                2,
                ',',
                ' ',
            ),
        ];
    }

    /**
     * @param array<string, mixed> $values
     * @param list<string> $keys
     * @return array<string, mixed>
     */
    private function fill(array $values, array $keys): array
    {
        foreach ($keys as $key) {
            $values[$key] ??= null;
        }

        return $values;
    }

    /**
     * @param list<array<string, mixed>> $lists
     */
    private function hasAlternatives(array $lists): bool
    {
        foreach ($lists as $list) {
            if (($list['role'] ?? null) === 'alternative') {
                return true;
            }
        }

        return false;
    }
}
