import type { DriftBoard } from '@app/api/WarehouseApi';

type Translate = (key: string, options?: Record<string, unknown>) => string;

/**
 * Produkty, których cena zakupu wyprzedziła cennik sprzedaży.
 *
 * Przyjęcie towaru aktualizuje cenę zakupu, ale cen sprzedaży nie rusza
 * — inaczej jedna dostawa przestawiłaby oferty już wysłane do klienta.
 * Ten ekran jest jedynym, co o rozjeździe mówi, więc pokazuje **starą
 * i nową cenę zakupu obok siebie**: skoro cena bierze się z ostatniej
 * dostawy, uzupełnienie stanu drożej zawyża koszt tego, co już leżało,
 * i człowiek musi to zobaczyć, zanim naciśnie „przelicz".
 */
export function PriceDrift({ board, t }: { board: DriftBoard | null; t: Translate }) {
  const rows = board?.rows ?? [];

  return (
    <div className="ge-stock ge-stock--drift">
      <div className="ge-stock__head">
        <span>{t('page.warehouse.column.code')}</span>
        <span>{t('page.warehouse.column.name')}</span>
        <span className="r">{t('page.warehouse.column.purchase_was')}</span>
        <span className="r">{t('page.warehouse.column.purchase_now')}</span>
        <span className="r">{t('page.warehouse.column.changed_at')}</span>
        <span className="r">{t('page.warehouse.column.coefficient')}</span>
        <span className="r">{t('page.warehouse.column.list_price')}</span>
        <span className="r">{t('page.warehouse.column.priced_at')}</span>
      </div>

      {rows.map((row) => (
        <div className="ge-stock__row ge-stock__row--short" key={row.product_id}>
          <span className="ge-quiet">{row.code ?? '—'}</span>
          <span className="ge-cell--wrap">{row.name}</span>
          <span className="r ge-quiet">{row.previous_purchase_price ?? '—'}</span>
          <span className="r ge-note--warn">{row.purchase_price}</span>
          <span className="r ge-quiet">{row.changed_at}</span>
          <span className="r ge-quiet">{row.coefficient}</span>
          <span className="r">{row.list_net_price ?? '—'}</span>
          <span className="r ge-quiet">{row.priced_at}</span>
        </div>
      ))}

      {rows.length === 0 && (
        <div className="ge-empty">{t('page.warehouse.drift_empty')}</div>
      )}
    </div>
  );
}
