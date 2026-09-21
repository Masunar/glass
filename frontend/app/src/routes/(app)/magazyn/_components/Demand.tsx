import { Link } from 'react-router';

import type { DemandBoard } from '@app/api/WarehouseApi';

import { decimal } from './decimal';

type Translate = (key: string, options?: Record<string, unknown>) => string;

/**
 * Czego potrzebują zlecenia, a czego nie ma na stanie.
 *
 * Jeden wiersz na zlecenie i produkt — sumowanie po produkcie dzieje
 * się po stronie serwera, bo zawyżone zapotrzebowanie to zamówienie
 * większe niż potrzeba.
 */
export function Demand({ board, t }: { board: DemandBoard | null; t: Translate }) {
  const rows = board?.rows ?? [];

  return (
    <div className="ge-stock ge-stock--demand">
      <div className="ge-stock__head">
        <span>{t('page.warehouse.column.order')}</span>
        <span>{t('page.warehouse.column.contractor')}</span>
        <span>{t('page.warehouse.column.status')}</span>
        <span>{t('page.warehouse.column.code')}</span>
        <span>{t('page.warehouse.column.name')}</span>
        <span className="r">{t('page.warehouse.column.needed')}</span>
        <span className="r">{t('page.warehouse.column.stock')}</span>
        <span className="r">{t('page.warehouse.column.missing')}</span>
      </div>

      {rows.map((row) => (
        <div className="ge-stock__row" key={`${row.order_id}-${row.product_id}`}>
          <span>
            <Link to={`/orders/${row.order_id}`}>#{row.order_number}</Link>
          </span>
          <span className="ge-cell--wrap">{row.contractor ?? '—'}</span>
          <span className="ge-quiet">{row.status ?? '—'}</span>
          <span className="ge-quiet">{row.code ?? '—'}</span>
          <span className="ge-cell--wrap">{row.name}</span>
          <span className="r">{decimal(row.needed)}</span>
          <span className="r ge-quiet">{decimal(row.in_stock)}</span>
          {/* Brak blokuje wejscie na produkcje, wiec swieci. */}
          <span className={row.missing > 0 ? 'r ge-note--warn' : 'r ge-quiet'}>
            {row.missing > 0 ? decimal(row.missing) : '—'}
          </span>
        </div>
      ))}

      {rows.length === 0 && (
        <div className="ge-empty">{t('page.warehouse.demand_empty')}</div>
      )}
    </div>
  );
}
