import type { OrderTotals } from '@app/api/OrdersApi';

type Translate = (key: string, values?: Record<string, unknown>) => string;

const money = (value: string) =>
  new Intl.NumberFormat('pl-PL', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Number(value));

/**
 * Rozbicie na stawki — to samo, co na dole faktury.
 *
 * Pokazuje się dopiero, gdy stawek jest więcej niż jedna albo gdy
 * część kwoty nie ma znanej stawki. Przy jednej stawce byłby to jeden
 * wiersz powtarzający to, co już stoi wyżej.
 */
export default function VatLines({
  totals,
  t,
}: {
  totals: OrderTotals;
  t: Translate;
}) {
  const unknown = Number(totals.unknown_net);

  if (!totals.mixed_vat && unknown === 0) {
    return null;
  }

  return (
    <section className="ge-section">
      <div className="ge-section__head">{t('page.orders.card.vat_split')}</div>
      <table className="ge-vat">
        <tbody>
          {totals.vat_lines.map((line) => (
            <tr key={line.rate}>
              <td className="ge-vat__rate">{line.rate}%</td>
              <td className="ge-vat__net">{money(line.net)}</td>
              <td className="ge-vat__vat">{money(line.vat)}</td>
              <td className="ge-vat__gross">{money(line.gross)}</td>
            </tr>
          ))}
          {unknown !== 0 && (
            <tr className="ge-vat__row--warn">
              <td className="ge-vat__rate">{t('page.orders.card.vat_none')}</td>
              <td className="ge-vat__net">{money(totals.unknown_net)}</td>
              <td className="ge-vat__vat">—</td>
              <td className="ge-vat__gross">—</td>
            </tr>
          )}
        </tbody>
      </table>
      {totals.unknown_reason && (
        <div className="ge-note ge-note--warn">{totals.unknown_reason}</div>
      )}
    </section>
  );
}
