import { decimal } from './decimal';
import { useState } from 'react';
import { Link } from 'react-router';

import type { ExtraDeliveryBoard } from '@app/api/WarehouseApi';

type Translate = (key: string, options?: Record<string, unknown>) => string;

const display = (value: string | null) =>
  value === null ? '—' : value.split('-').reverse().join('.');

/**
 * Dostawy dodatkowe do zleceń (uwaga klienta 25.09, 3c).
 *
 * Osobny dokument, nie zamówienie do dostawcy: zawsze dla jednego
 * zlecenia i z powodem. Zakłada się go z listy kompletacji, przy
 * zleceniu, którego dotyczy — tutaj się go przyjmuje albo anuluje.
 */
export function ExtraDeliveries({
  board,
  t,
  status,
  onStatus,
  onReceive,
  onCancel,
}: {
  board: ExtraDeliveryBoard | null;
  t: Translate;
  status: string;
  onStatus: (value: string) => void;
  onReceive: (id: number, receivedAt: string) => void;
  onCancel: (id: number) => void;
}) {
  const rows = board?.rows ?? [];
  const [dates, setDates] = useState<Record<number, string>>({});

  return (
    <>
      <div className="ge-segbar">
        <nav
          className="ge-seg ge-seg--filter"
          aria-label={t('page.warehouse.tab_extra')}
        >
          {(board?.filters ?? []).map((filter) => {
            const here = filter.code === status;

            return (
              <button
                key={filter.code}
                type="button"
                className={[
                  'ge-seg__item',
                  here ? 'is-active' : '',
                  filter.count === 0 && !here ? 'ge-seg__item--empty' : '',
                ]
                  .filter(Boolean)
                  .join(' ')}
                aria-pressed={here}
                onClick={() => onStatus(filter.code)}
              >
                <span>{filter.name}</span>
                {filter.count === 0 ? null : (
                  <span className="ge-seg__count">{filter.count}</span>
                )}
              </button>
            );
          })}
        </nav>
      </div>

      <p className="ge-lead">{t('page.warehouse.extra.lead')}</p>

      <div className="ge-stock ge-stock--extra">
        <div className="ge-stock__head">
          <span>{t('page.warehouse.extra.column.number')}</span>
          <span>{t('page.warehouse.column.order')}</span>
          <span>{t('page.warehouse.extra.column.reason')}</span>
          <span>{t('page.warehouse.extra.column.items')}</span>
          <span>{t('page.warehouse.column.supplier')}</span>
          <span className="r">{t('page.warehouse.column.expected_at')}</span>
          <span>{t('page.warehouse.column.order_status')}</span>
          <span />
        </div>

        {rows.map((row) => (
          <div className="ge-stock__row" key={row.id}>
            <span>DD/{row.number}</span>
            <span className="ge-cell--wrap">
              <Link to={`/orders/${row.order_id}`} className="ge-link">
                #{row.order_number}
              </Link>{' '}
              <span className="ge-quiet">{row.contractor ?? ''}</span>
            </span>
            <span>
              {row.reason_label}
              {row.note && <div className="ge-note">{row.note}</div>}
            </span>
            <span className="ge-cell--wrap">
              {row.items
                .map(
                  (item) => `${item.name ?? '—'} × ${decimal(item.quantity)}`,
                )
                .join(', ')}
            </span>
            <span className="ge-quiet">{row.supplier ?? '—'}</span>
            <span className="r">{display(row.expected_at)}</span>
            <span className="ge-quiet">
              {row.status === 'received'
                ? t('page.warehouse.extra.received_on', {
                    date: display(row.received_at),
                  })
                : row.status_label}
            </span>
            <span className="ge-stock__actions">
              {row.status === 'expected' && (
                <>
                  <input
                    type="date"
                    className="ge-uf__input"
                    aria-label={t('page.warehouse.received_at')}
                    value={dates[row.id] ?? ''}
                    onChange={(event) =>
                      setDates((current) => ({
                        ...current,
                        [row.id]: event.target.value,
                      }))
                    }
                  />
                  <button
                    type="button"
                    className="ge-act--go"
                    onClick={() => onReceive(row.id, dates[row.id] ?? '')}
                  >
                    {t('page.warehouse.receive')}
                  </button>
                  <button type="button" onClick={() => onCancel(row.id)}>
                    {t('page.warehouse.extra.cancel')}
                  </button>
                </>
              )}
            </span>
          </div>
        ))}

        {board !== null && rows.length === 0 && (
          <div className="ge-empty">{t('page.warehouse.extra.empty')}</div>
        )}
      </div>
    </>
  );
}
