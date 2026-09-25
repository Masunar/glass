import { decimal } from './decimal';
import { Link } from 'react-router';

import type { PickingBoard, PickingRow } from '@app/api/WarehouseApi';
import { WarehouseApi } from '@app/api/WarehouseApi';

type Translate = (key: string, options?: Record<string, unknown>) => string;

const iso = (date: Date) => {
  const local = new Date(date.getTime() - date.getTimezoneOffset() * 60000);

  return local.toISOString().slice(0, 10);
};

const plus = (days: number) => {
  const date = new Date();
  date.setDate(date.getDate() + days);

  return iso(date);
};

const display = (value: string) => value.split('-').reverse().join('.');

/** Szybkie zakresy — magazynier pyta o „dziś", „jutro", „ten tydzień". */
export const PICKING_RANGES: {
  key: string;
  from: () => string;
  to: () => string;
}[] = [
  { key: 'today', from: () => plus(0), to: () => plus(0) },
  { key: 'tomorrow', from: () => plus(1), to: () => plus(1) },
  { key: 'week', from: () => plus(0), to: () => plus(6) },
];

/**
 * Kompletacja okuć — lista zleceń na termin (uwaga klienta 25.09, 3d).
 *
 * Grupowanie po dniu, bo magazynier myśli „co na jutro", a nie
 * „zlecenie 24046". Zaległe nieprzygotowane stoją na górze, bez terminu
 * na końcu — przeoczone nie znika tylko dlatego, że jego dzień minął.
 */
export function Picking({
  board,
  t,
  from,
  to,
  onRange,
  onPrepared,
  onExtra,
}: {
  board: PickingBoard | null;
  t: Translate;
  from: string;
  to: string;
  onRange: (from: string, to: string) => void;
  onPrepared: (row: PickingRow, prepared: boolean) => void;
  onExtra: (row: PickingRow) => void;
}) {
  const rows = board?.rows ?? [];
  const groups: { key: string; label: string; rows: PickingRow[] }[] = [];

  for (const row of rows) {
    const key =
      row.deadline === null ? 'none' : row.is_late ? 'late' : row.deadline;
    let group = groups.find((entry) => entry.key === key);

    if (!group) {
      group = {
        key,
        label:
          key === 'none'
            ? t('page.warehouse.picking.no_date')
            : key === 'late'
              ? t('page.warehouse.picking.late')
              : display(key),
        rows: [],
      };
      groups.push(group);
    }

    group.rows.push(row);
  }

  return (
    <>
      <div className="ge-segbar">
        <nav
          className="ge-seg ge-seg--filter"
          aria-label={t('page.warehouse.tab_picking')}
        >
          {PICKING_RANGES.map((range) => {
            const here = range.from() === from && range.to() === to;

            return (
              <button
                key={range.key}
                type="button"
                className={here ? 'ge-seg__item is-active' : 'ge-seg__item'}
                aria-pressed={here}
                onClick={() => onRange(range.from(), range.to())}
              >
                <span>{t(`page.warehouse.picking.range_${range.key}`)}</span>
              </button>
            );
          })}
        </nav>
        <span className="ge-segbar__end ge-pick__range">
          <input
            type="date"
            className="ge-uf__input"
            value={from}
            aria-label={t('page.warehouse.picking.from')}
            onChange={(event) =>
              event.target.value &&
              onRange(
                event.target.value,
                to < event.target.value ? event.target.value : to,
              )
            }
          />
          <span className="ge-quiet">–</span>
          <input
            type="date"
            className="ge-uf__input"
            value={to}
            aria-label={t('page.warehouse.picking.to')}
            onChange={(event) =>
              event.target.value &&
              onRange(
                from > event.target.value ? event.target.value : from,
                event.target.value,
              )
            }
          />
          <a
            className="ge-pick__print"
            href={WarehouseApi.pickingPdfUrl(from, to)}
            target="_blank"
            rel="noreferrer"
          >
            {t('page.warehouse.picking.print')}
          </a>
        </span>
      </div>

      {board !== null && (
        <p className="ge-lead">
          {t('page.warehouse.picking.summary', {
            orders: board.summary.orders,
            prepared: board.summary.prepared,
            short: board.summary.short,
          })}
        </p>
      )}

      {groups.map((group) => (
        <section className="ge-pickgroup" key={group.key}>
          <h3
            className={
              group.key === 'late'
                ? 'ge-pickgroup__head ge-pickgroup__head--late'
                : 'ge-pickgroup__head'
            }
          >
            {group.label}
            <span className="ge-quiet"> · {group.rows.length}</span>
          </h3>

          {group.rows.map((row) => (
            <article
              key={row.id}
              className={row.prepared ? 'ge-pickorder is-done' : 'ge-pickorder'}
            >
              <div className="ge-pickorder__head">
                <Link to={`/orders/${row.id}`} className="ge-link">
                  #{row.number}
                </Link>
                <span className="ge-cell--wrap">{row.contractor ?? '—'}</span>
                <span className="ge-quiet">
                  {row.status ?? '—'}
                  {row.deadline !== null && group.key === 'late'
                    ? ` · ${display(row.deadline)}`
                    : ''}
                </span>
                <span className="ge-pickorder__state">
                  {row.prepared ? (
                    <span className="ge-quiet">
                      {t('page.warehouse.picking.prepared_by', {
                        who: row.prepared.by ?? '—',
                        at: row.prepared.at,
                      })}
                    </span>
                  ) : row.missing > 0 ? (
                    <span className="ge-note--warn">
                      {t('page.warehouse.picking.missing', {
                        count: row.missing,
                      })}
                    </span>
                  ) : null}
                </span>
                <span className="ge-stock__actions">
                  <button type="button" onClick={() => onExtra(row)}>
                    {t('page.warehouse.picking.extra')}
                  </button>
                  <button
                    type="button"
                    className={row.prepared ? undefined : 'ge-act--go'}
                    onClick={() => onPrepared(row, row.prepared === null)}
                  >
                    {row.prepared
                      ? t('page.warehouse.picking.undo')
                      : t('page.warehouse.picking.mark')}
                  </button>
                </span>
              </div>

              <div className="ge-pickorder__lines">
                {row.fittings.map((line) => (
                  <div className="ge-pickline" key={line.product_id}>
                    <span className="ge-quiet">{line.code ?? '—'}</span>
                    <span className="ge-cell--wrap">{line.name}</span>
                    <span className="r">{decimal(line.quantity)}</span>
                    <span
                      className={
                        line.state === 'short'
                          ? 'ge-note--warn'
                          : line.state === 'issued'
                            ? 'ge-quiet'
                            : undefined
                      }
                    >
                      {t(`page.warehouse.picking.state_${line.state}`, {
                        stock: decimal(line.in_stock),
                      })}
                    </span>
                  </div>
                ))}

                {row.extra.map((delivery) => (
                  <div
                    className="ge-pickline ge-pickline--extra"
                    key={`dd-${delivery.id}`}
                  >
                    <span className="ge-quiet">DD/{delivery.number}</span>
                    <span className="ge-cell--wrap">
                      {delivery.reason_label}:{' '}
                      {delivery.items
                        .map(
                          (item) =>
                            `${item.name ?? '—'} × ${decimal(item.quantity)}`,
                        )
                        .join(', ')}
                    </span>
                    <span />
                    <span
                      className={
                        delivery.status === 'expected'
                          ? 'ge-note--warn'
                          : 'ge-quiet'
                      }
                    >
                      {delivery.status === 'expected' && delivery.expected_at
                        ? t('page.warehouse.extra.expected_on', {
                            date: display(delivery.expected_at),
                          })
                        : delivery.status_label}
                    </span>
                  </div>
                ))}
              </div>
            </article>
          ))}
        </section>
      ))}

      {board !== null && rows.length === 0 && (
        <div className="ge-empty">{t('page.warehouse.picking.empty')}</div>
      )}
    </>
  );
}
