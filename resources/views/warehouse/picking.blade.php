{{--
    Lista kompletacji okuc (uwaga klienta 25.09, punkt 3d).

    Kartka dla magazyniera: zlecenia z okuciami na dany termin, przy
    kazdej pozycji kratka do odhaczenia. Stan „brak" drukuje sie wprost
    — magazynier ma to wiedziec, zanim pojdzie na regal.
--}}
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <title>Kompletacja okuć</title>
    <style>
        @page { margin: 14mm 12mm 16mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; line-height: 1.35; color: #1a1a1a; }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; }
        .r { text-align: right; }
        .c { text-align: center; }
        .muted { color: #5a5a5a; font-size: 8pt; }
        .title { font-size: 14pt; font-weight: bold; border-bottom: 1.2pt solid #1a1a1a; padding-bottom: 3pt; margin-bottom: 8pt; }
        .order { margin-top: 10pt; page-break-inside: avoid; }
        .order__head td { background: #efefef; padding: 4pt 5pt; font-weight: bold; }
        .order__head .muted { font-weight: normal; }
        .lines td { padding: 3pt 5pt; border-bottom: 0.4pt solid #d8d8d8; }
        .box { display: inline-block; width: 9pt; height: 9pt; border: 0.8pt solid #1a1a1a; }
        .short { color: #b23b2e; font-weight: bold; }
        .done { color: #2f6b3a; }
        .extra { font-size: 8pt; color: #3a3a3a; padding: 3pt 5pt; }
    </style>
</head>
<body>

<table>
    <tr>
        <td>
            <div class="title">Kompletacja okuć · {{ $from }}@if ($to !== $from) – {{ $to }}@endif</div>
        </td>
        <td class="r muted">
            @if ($seller['name']) {{ $seller['name'] }}<br> @endif
            Wydrukowano {{ $printed_at }}
        </td>
    </tr>
</table>

@if (count($rows) === 0)
    <p class="muted">Na ten zakres nie ma zleceń z okuciami do przygotowania.</p>
@endif

@foreach ($rows as $row)
    <table class="order">
        <tr class="order__head">
            <td>
                #{{ $row['number'] }} · {{ $row['contractor'] ?? '—' }}
                <span class="muted">· {{ $row['status'] }}</span>
            </td>
            <td class="r">
                @if ($row['deadline'])
                    na {{ \Carbon\Carbon::parse($row['deadline'])->format('d.m.Y') }}@if ($row['is_late']) (zaległe)@endif
                @else
                    bez terminu
                @endif
                @if ($row['prepared'])
                    <span class="muted"> · przygotowane {{ $row['prepared']['by'] }}</span>
                @endif
            </td>
        </tr>
        <tr>
            <td colspan="2" style="padding: 0;">
                <table class="lines">
                    @foreach ($row['fittings'] as $line)
                        <tr>
                            <td style="width: 16pt;" class="c"><span class="box"></span></td>
                            <td style="width: 70pt;">{{ $line['code'] ?? '' }}</td>
                            <td>{{ $line['name'] }}</td>
                            <td class="r" style="width: 50pt;">{{ rtrim(rtrim(number_format($line['quantity'], 3, ',', ''), '0'), ',') }} szt.</td>
                            <td class="r" style="width: 70pt;">
                                @if ($line['state'] === 'issued')
                                    <span class="done">wydane</span>
                                @elseif ($line['state'] === 'short')
                                    <span class="short">brak ({{ rtrim(rtrim(number_format($line['in_stock'], 3, ',', ''), '0'), ',') ?: '0' }})</span>
                                @else
                                    na stanie
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </table>
                @foreach ($row['extra'] as $delivery)
                    <div class="extra">
                        Dostawa dodatkowa DD/{{ $delivery['number'] }} · {{ $delivery['reason_label'] }} · {{ mb_strtolower($delivery['status_label']) }}:
                        @foreach ($delivery['items'] as $item)
                            {{ $item['name'] }} × {{ rtrim(rtrim(number_format($item['quantity'], 3, ',', ''), '0'), ',') }}@if (!$loop->last), @endif
                        @endforeach
                    </div>
                @endforeach
            </td>
        </tr>
    </table>
@endforeach

</body>
</html>
