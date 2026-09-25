{{--
    Wydruk zamowienia do dostawcy (uwaga klienta 25.09, punkt 3b).

    Kartka do sprawdzenia dostawy: kolumna „Przyjeto" jest pusta
    celowo — magazynier odhacza ja dlugopisem przy rozladunku, a system
    przyjmuje potem to, co odhaczyl. Uklad na tabelach i DejaVu Sans,
    jak na ofercie: dompdf nie zna flexboxa, a domyslna rodzina gubi
    polskie znaki.
--}}
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <title>Zamówienie {{ $number }}</title>
    <style>
        @page { margin: 16mm 14mm 18mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5pt; line-height: 1.4; color: #1a1a1a; }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; }
        .r { text-align: right; }
        .c { text-align: center; }
        .brand { font-size: 12pt; font-weight: bold; }
        .muted { color: #5a5a5a; font-size: 8.5pt; }
        .title { font-size: 15pt; font-weight: bold; border-bottom: 1.2pt solid #1a1a1a; padding-bottom: 4pt; margin: 10pt 0; }
        .label { font-size: 7.5pt; letter-spacing: 0.08em; text-transform: uppercase; color: #5a5a5a; padding-bottom: 2pt; }
        .party { width: 50%; padding-right: 10pt; }
        .items { margin-top: 12pt; }
        .items th { font-size: 7.5pt; letter-spacing: 0.06em; text-transform: uppercase; color: #3a3a3a; text-align: left; padding: 4pt 5pt 3pt; border-bottom: 0.8pt solid #9a9a9a; }
        .items td { padding: 5pt 5pt; border-bottom: 0.4pt solid #d8d8d8; }
        .box { display: inline-block; width: 10pt; height: 10pt; border: 0.8pt solid #1a1a1a; }
        .write { border-bottom: 0.6pt solid #9a9a9a; height: 12pt; }
        .note { margin-top: 10pt; padding: 6pt 8pt; background: #f4f4f4; font-size: 8.5pt; }
        .sign { margin-top: 26pt; }
        .sign td { width: 50%; padding-right: 20pt; }
        .line { border-top: 0.6pt solid #1a1a1a; padding-top: 3pt; font-size: 8pt; color: #5a5a5a; }
    </style>
</head>
<body>

<table>
    <tr>
        <td>
            @if ($seller['name'])
                <div class="brand">{{ $seller['name'] }}</div>
            @endif
            <div class="muted">
                @if ($seller['address']) {{ $seller['address'] }}<br> @endif
                @if ($seller['phone']) tel. {{ $seller['phone'] }} @endif
            </div>
        </td>
        <td class="r muted">
            Wystawiono {{ $created_at }}<br>
            Stan: {{ $status }}
        </td>
    </tr>
</table>

<div class="title">Zamówienie nr {{ $number }}</div>

<table>
    <tr>
        <td class="party">
            <div class="label">Dostawca</div>
            <strong>{{ $supplier['name'] }}</strong><br>
            @if ($supplier['contact']) {{ $supplier['contact'] }}<br> @endif
            @if ($supplier['phone']) tel. {{ $supplier['phone'] }} @endif
            @if ($supplier['email']) · {{ $supplier['email'] }} @endif
        </td>
        <td class="party">
            <div class="label">Terminy</div>
            Zamówiono: {{ $ordered_at ?? '—' }}<br>
            Oczekiwana dostawa: {{ $expected_at ?? '—' }}
        </td>
    </tr>
</table>

<table class="items">
    <tr>
        <th style="width: 18pt;">Lp.</th>
        <th style="width: 70pt;">Kod</th>
        <th>Towar</th>
        <th class="r" style="width: 52pt;">Zamówiono</th>
        <th class="r" style="width: 52pt;">Przyjęto dotąd</th>
        <th class="c" style="width: 44pt;">Jest</th>
        <th style="width: 90pt;">Uwagi</th>
    </tr>
    @foreach ($items as $index => $item)
        <tr>
            <td>{{ $index + 1 }}</td>
            <td>{{ $item['code'] ?? '' }}</td>
            <td>{{ $item['name'] }}</td>
            <td class="r">{{ $item['ordered'] }} {{ $item['unit'] }}</td>
            <td class="r">{{ $item['received'] }}</td>
            <td class="c"><span class="box"></span></td>
            <td><div class="write"></div></td>
        </tr>
    @endforeach
</table>

@if ($note)
    <div class="note">{{ $note }}</div>
@endif

<table class="sign">
    <tr>
        <td><div class="line">Przyjął (data, podpis)</div></td>
        <td><div class="line">Uwagi do dostawy</div></td>
    </tr>
</table>

</body>
</html>
