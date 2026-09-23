{{--
    Wydruk oferty.

    Szablon jest **osobny od CSS aplikacji** i to nie jest zaniedbanie.
    dompdf renderuje HTML w standardzie sprzed flexboxa: nie ma `grid`
    ani `flex`, uklad idzie na tabelach, a rodzina pisma musi byc jawnie
    `DejaVu Sans`, bo domyslna gubi polskie znaki.

    Wszystko, co tu widac, pochodzi z migawki oferty — nie ze zlecenia.
    Wydruk sprzed miesiaca ma dzis wygladac tak samo.

    Puste pole zostaje puste. Gdy w slowniku nie ma nazwy firmy albo
    rachunku, wiersz po prostu nie powstaje; podstawienie czegokolwiek
    dalo by dokument, ktory wyglada na kompletny.
--}}
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <title>Oferta {{ $number }}</title>
    <style>
        @page { margin: 18mm 15mm 20mm; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9.5pt;
            line-height: 1.45;
            color: #1a1a1a;
        }

        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; }
        .r { text-align: right; }

        .head td { padding-bottom: 10pt; }
        .brand { font-size: 13pt; font-weight: bold; letter-spacing: 0.5pt; }
        .muted { color: #5a5a5a; font-size: 8.5pt; }

        /* Pas podgladu. Nie ozdoba: bez niego wydruk podgladu jest nie
           do odroznienia od oferty, ktora poszla do klienta — a ta
           pierwsza nie zostawia po sobie zadnego sladu w systemie. */
        .draft {
            border: 1.5pt solid #b23b2e;
            color: #b23b2e;
            padding: 5pt 8pt;
            margin-bottom: 10pt;
            font-size: 9pt;
            font-weight: bold;
        }
        .draft__why { display: block; font-weight: normal; font-size: 8pt; }

        .title {
            font-size: 15pt;
            font-weight: bold;
            border-bottom: 1.2pt solid #1a1a1a;
            padding-bottom: 4pt;
            margin-bottom: 10pt;
        }

        .party { width: 50%; padding-right: 10pt; }
        .label {
            font-size: 7.5pt;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #5a5a5a;
            padding-bottom: 2pt;
        }

        .list { margin-top: 12pt; }
        .list__head {
            background: #f0f0f0;
            font-weight: bold;
            padding: 4pt 6pt;
            border-top: 0.8pt solid #9a9a9a;
        }
        .list__alt { font-weight: normal; color: #5a5a5a; }

        .items th {
            font-size: 7.5pt;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #3a3a3a;
            text-align: left;
            padding: 4pt 6pt 3pt;
            border-bottom: 0.6pt solid #9a9a9a;
        }
        .items td { padding: 3pt 6pt; border-bottom: 0.4pt solid #d8d8d8; }

        .sum { margin-top: 14pt; }
        .sum td { padding: 3pt 6pt; }
        .sum .total td {
            border-top: 1.2pt solid #1a1a1a;
            font-size: 11pt;
            font-weight: bold;
            padding-top: 5pt;
        }

        .terms { margin-top: 18pt; font-size: 8.5pt; }
        .terms p { margin: 0 0 3pt; }

        .note {
            margin-top: 12pt;
            padding: 6pt 8pt;
            background: #f6f6f6;
            font-size: 8.5pt;
        }
    </style>
</head>
<body>

@if ($isPreview)
    <div class="draft">
        PODGLĄD — oferta nie została wystawiona
        <span class="draft__why">
            Ten dokument nie istnieje w systemie i nie ma numeru. Numer
            nadaje się dopiero przy wystawieniu.
        </span>
    </div>
@endif

<table class="head">
    <tr>
        <td>
            @if ($seller['name'])
                <div class="brand">{{ $seller['name'] }}</div>
            @endif
            <div class="muted">
                @if ($seller['address']) {{ $seller['address'] }}<br> @endif
                @if ($seller['tax_id']) NIP {{ $seller['tax_id'] }}<br> @endif
                @if ($seller['phone']) tel. {{ $seller['phone'] }} @endif
                @if ($seller['email']) · {{ $seller['email'] }} @endif
            </div>
        </td>
        <td class="r muted">
            {{ $isPreview ? 'Data podglądu' : 'Data wystawienia' }}: {{ $issuedOn }}<br>
            @if ($validUntil) Oferta ważna do: {{ $validUntil }}<br> @endif
            Dotyczy zlecenia: {{ $order['number'] }}
        </td>
    </tr>
</table>

<div class="title">Oferta @if ($number) {{ $number }} @endif</div>

<table>
    <tr>
        <td class="party">
            <div class="label">Dla</div>
            <strong>{{ $buyer['name'] ?? '—' }}</strong><br>
            <span class="muted">
                @if ($buyer['address']) {{ $buyer['address'] }}<br> @endif
                @if ($buyer['tax_id']) NIP {{ $buyer['tax_id'] }} @endif
            </span>
        </td>
        <td class="party">
            <div class="label">Ceny</div>
            {{ $isGross ? 'Kwoty brutto (z VAT)' : 'Kwoty netto (bez VAT)' }}
        </td>
    </tr>
</table>

@foreach ($lists as $list)
    <div class="list">
        <table>
            <tr>
                <td class="list__head">
                    {{ ($list['name'] ?? null) ?: 'Lista ' . $list['number'] }}
                    @if (($list['role'] ?? null) === 'alternative')
                        <span class="list__alt">· wariant</span>
                    @endif
                </td>
                <td class="list__head r">
                    {{-- Oferta brutto bez znanej stawki nie pokazuje kwoty
                         brutto. Podstawienie netto w jej miejsce byloby
                         kwota o cale 23 % za niska. --}}
                    @if ($isGross && ($list['gross'] ?? null) === null)
                        {{ $money($list['net']) }} zł netto
                    @elseif ($isGross)
                        {{ $money($list['gross'] ?? 0) }} zł
                    @else
                        {{ $money($list['net']) }} zł
                    @endif
                </td>
            </tr>
        </table>

        @if (count($list['items'] ?? []) > 0)
            <table class="items">
                <thead>
                <tr>
                    <th style="width: 40%">Pozycja</th>
                    <th style="width: 20%">Wymiar</th>
                    <th style="width: 20%">Obróbka</th>
                    <th style="width: 8%" class="r">Ilość</th>
                    <th style="width: 12%" class="r">Wartość</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($list['items'] ?? [] as $item)
                    <tr>
                        <td>{{ $item['name'] }}</td>
                        <td>
                            @if ($item['width_mm'] && $item['height_mm'])
                                {{ $item['width_mm'] }} × {{ $item['height_mm'] }} mm
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ count($item['processes']) > 0 ? implode(', ', $item['processes']) : '—' }}</td>
                        <td class="r">{{ 0 + $item['quantity'] }}</td>
                        <td class="r">{{ $money($item['amount']) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif

        @if ($list['comment'] ?? null)
            <div class="muted" style="padding: 3pt 6pt;">{{ $list['comment'] }}</div>
        @endif
    </div>
@endforeach

@if ($sum['is_shown'] ?? false)
    <table class="sum">
        @if (($sum['mode'] ?? null) === 'components' && $hasAlternatives)
            <tr>
                <td colspan="2" class="muted">
                    Suma nie obejmuje wariantów — są alternatywą, nie dodatkiem.
                </td>
            </tr>
        @endif
        <tr>
            <td>Razem netto</td>
            <td class="r">{{ $money($sum['net'] ?? 0) }} zł</td>
        </tr>
        @foreach ($vatLines as $line)
            <tr class="muted">
                <td>VAT {{ $line['rate'] }}%</td>
                <td class="r">{{ $money($line['vat']) }} zł</td>
            </tr>
        @endforeach
        @if (($sum['gross'] ?? null) !== null)
            <tr class="total">
                <td>Razem brutto</td>
                <td class="r">{{ $money($sum['gross'] ?? 0) }} zł</td>
            </tr>
        @endif
    </table>
@endif

@if ($comment)
    <div class="note">{{ $comment }}</div>
@endif

@if ($order['offer_comment'])
    <div class="note">{{ $order['offer_comment'] }}</div>
@endif

<div class="terms">
    @foreach ($texts as $text)
        @if ($text)
            <p>{{ $text }}</p>
        @endif
    @endforeach
</div>

</body>
</html>
