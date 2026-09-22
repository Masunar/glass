import type { OrderTotals } from '@app/api/OrdersApi';

type Translate = (key: string, values?: Record<string, unknown>) => string;

const money = (value: string) =>
  new Intl.NumberFormat('pl-PL', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Number(value));

/**
 * Zdanie pod kwotą netto.
 *
 * Trzy stany, nie jeden. Jedna stawka — brutto i stawka. Kilka stawek —
 * samo brutto łączne, bo „23 %" przy zleceniu z montażem na 8 % byłoby
 * nieprawdą, a średnia z dwóch stawek nie istnieje w żadnym przepisie.
 * Stawka nieznana — brutto nie ma i mówimy, czego brakuje, zamiast
 * pokazać zero.
 */
export function vatNote(
  totals: OrderTotals,
  t: Translate,
): { text: string; warn: boolean } {
  if (Number(totals.unknown_net) !== 0) {
    return {
      warn: true,
      text:
        totals.unknown_reason ??
        t('page.orders.card.vat_unknown', {
          amount: money(totals.unknown_net),
        }),
    };
  }

  if (totals.gross === null) {
    return { warn: true, text: t('page.orders.card.no_invoice_type') };
  }

  if (totals.mixed_vat) {
    return {
      warn: false,
      text: t('page.orders.card.vat_mixed', {
        gross: money(totals.gross),
        rates: totals.vat_lines.map((line) => `${line.rate}%`).join(' + '),
      }),
    };
  }

  return {
    warn: false,
    text: t('page.orders.card.value_note', {
      vat: totals.vat_rate,
      gross: money(totals.gross),
    }),
  };
}
