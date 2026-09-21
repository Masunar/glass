import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router';

import { useTranslation } from '@salvon/hooks/useTranslation';
import { notifyError, notifySuccess } from '@salvon/utils/notify';

import type {
  DemandBoard,
  StockBoard,
  StockRow,
} from '@app/api/WarehouseApi';
import { WarehouseApi } from '@app/api/WarehouseApi';

const decimal = (value: number) =>
  Number.isInteger(value)
    ? String(value)
    : value.toFixed(3).replace(/0+$/, '').replace(/\.$/, '');

/**
 * Magazyn: stany i zapotrzebowanie zleceń.
 *
 * Dwie zakładki, bo to dwa różne pytania. „Stany" odpowiadają na
 * „co zamówić, żeby nie zabrakło", „Okucia w zamówieniach" — na
 * „czego brakuje pod to, co już sprzedaliśmy". Pierwsze patrzy na
 * progi, drugie na konkretne zlecenia.
 *
 * Na razie same okucia — to jedyna sekcja, która ma stany.
 */
export default function Page() {
  const t = useTranslation();

  const [tab, setTab] = useState<'levels' | 'demand'>('levels');
  const [levels, setLevels] = useState<StockBoard | null>(null);
  const [demand, setDemand] = useState<DemandBoard | null>(null);
  const [query, setQuery] = useState('');
  const [shortages, setShortages] = useState(false);
  const [editing, setEditing] = useState<number | null>(null);

  const load = useCallback(async () => {
    if (tab === 'levels') {
      const { content } = await WarehouseApi.levels(query, shortages);
      const data: StockBoard | undefined = content?.data;

      if (data) {
        setLevels(data);
      }

      return;
    }

    const { content } = await WarehouseApi.demand();
    const data: DemandBoard | undefined = content?.data;

    if (data) {
      setDemand(data);
    }
  }, [tab, query, shortages]);

  useEffect(() => {
    void load();
  }, [load]);

  const saveThresholds = async (row: StockRow, min: number, max: number) => {
    const { response } = await WarehouseApi.thresholds(row.product_id, min, max);

    if (!response.success) {
      notifyError(t('api.ise'));

      return;
    }

    setEditing(null);
    notifySuccess(t('api.save_success'));
    void load();
  };

  const count = async (row: StockRow, counted: number) => {
    const { response } = await WarehouseApi.count(
      row.product_id,
      counted,
      t('page.warehouse.count_note'),
    );

    if (!response.success) {
      notifyError(t('api.ise'));

      return;
    }

    notifySuccess(t('api.save_success'));
    void load();
  };

  return (
    <>
      <header className="ge-head">
        <div>
          <div className="ge-head__kicker">{t('page.module.mag')}</div>
          <h1 className="ge-head__title">{t('page.warehouse.title')}</h1>
        </div>
      </header>

      <div className="ge-segbar">
        <nav className="ge-seg ge-seg--filter" aria-label={t('page.warehouse.title')}>
          <button
            type="button"
            className={
              tab === 'levels' ? 'ge-seg__item is-active' : 'ge-seg__item'
            }
            onClick={() => setTab('levels')}
          >
            <span>{t('page.warehouse.tab_levels')}</span>
            {levels && levels.summary.to_order > 0 && (
              <span className="ge-seg__count">{levels.summary.to_order}</span>
            )}
          </button>
          <button
            type="button"
            className={
              tab === 'demand' ? 'ge-seg__item is-active' : 'ge-seg__item'
            }
            onClick={() => setTab('demand')}
          >
            <span>{t('page.warehouse.tab_demand')}</span>
            {demand && demand.summary.missing > 0 && (
              <span className="ge-seg__count">{demand.summary.missing}</span>
            )}
          </button>
        </nav>

        {tab === 'levels' && (
          <>
            <input
              className="ge-uf__input"
              style={{ maxWidth: 240 }}
              placeholder={t('page.warehouse.search')}
              value={query}
              onChange={(event) => setQuery(event.target.value)}
            />
            <label className="ge-toggle">
              <input
                type="checkbox"
                checked={shortages}
                onChange={(event) => setShortages(event.target.checked)}
              />
              <span className="ge-toggle__track" />
              {t('page.warehouse.only_shortages')}
            </label>
          </>
        )}
      </div>

      {tab === 'levels' ? (
        <div className="ge-stock">
          <div className="ge-stock__head">
            <span>{t('page.warehouse.column.code')}</span>
            <span>{t('page.warehouse.column.name')}</span>
            <span>{t('page.warehouse.column.finish')}</span>
            <span className="r">{t('page.warehouse.column.stock')}</span>
            <span className="r">{t('page.warehouse.column.reserved')}</span>
            <span className="r">{t('page.warehouse.column.available')}</span>
            <span className="r">{t('page.warehouse.column.min')}</span>
            <span className="r">{t('page.warehouse.column.max')}</span>
            <span className="r">{t('page.warehouse.column.to_order')}</span>
            <span />
          </div>

          {(levels?.rows ?? []).map((row) => (
            <StockLine
              key={row.product_id}
              row={row}
              t={t}
              editing={editing === row.product_id}
              onEdit={() => setEditing(row.product_id)}
              onCancel={() => setEditing(null)}
              onSave={(min, max) => void saveThresholds(row, min, max)}
              onCount={(counted) => void count(row, counted)}
            />
          ))}

          {levels?.rows.length === 0 && (
            <div className="ge-empty">{t('page.warehouse.empty')}</div>
          )}
        </div>
      ) : (
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

          {(demand?.rows ?? []).map((row) => (
            <div
              className="ge-stock__row"
              key={`${row.order_id}-${row.product_id}`}
            >
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

          {demand?.rows.length === 0 && (
            <div className="ge-empty">{t('page.warehouse.demand_empty')}</div>
          )}
        </div>
      )}
    </>
  );
}

function StockLine({
  row,
  t,
  editing,
  onEdit,
  onCancel,
  onSave,
  onCount,
}: {
  row: StockRow;
  t: (key: string, options?: Record<string, unknown>) => string;
  editing: boolean;
  onEdit: () => void;
  onCancel: () => void;
  onSave: (min: number, max: number) => void;
  onCount: (counted: number) => void;
}) {
  const [min, setMin] = useState(String(row.min));
  const [max, setMax] = useState(String(row.max));
  const [counted, setCounted] = useState('');

  return (
    <div
      className={
        row.to_order > 0 ? 'ge-stock__row ge-stock__row--short' : 'ge-stock__row'
      }
    >
      <span className="ge-quiet">{row.code ?? '—'}</span>
      <span className="ge-cell--wrap">{row.name}</span>
      <span className="ge-quiet">{row.finish ?? '—'}</span>
      {/* Stan ponizej progu swieci — to jest cale pytanie tego ekranu. */}
      <span className={row.quantity < row.min ? 'r ge-note--warn' : 'r'}>
        {decimal(row.quantity)}
      </span>
      <span className="r ge-quiet">
        {row.reserved > 0 ? decimal(row.reserved) : '—'}
      </span>
      {/* Dostepne ponizej zera znaczy, ze obiecano wiecej, niz lezy. */}
      <span className={row.available < 0 ? 'r ge-note--warn' : 'r ge-quiet'}>
        {decimal(row.available)}
      </span>

      {editing ? (
        <>
          <span className="r">
            <input
              className="ge-uf__input r"
              value={min}
              onChange={(event) => setMin(event.target.value)}
            />
          </span>
          <span className="r">
            <input
              className="ge-uf__input r"
              value={max}
              onChange={(event) => setMax(event.target.value)}
            />
          </span>
          <span className="r ge-dim">{decimal(row.to_order)}</span>
          <span className="ge-stock__actions">
            <button type="button" onClick={() => onSave(Number(min), Number(max))}>
              {t('save')}
            </button>
            <button type="button" onClick={onCancel}>
              {t('cancel')}
            </button>
          </span>
        </>
      ) : (
        <>
          <span className="r ge-quiet">{decimal(row.min)}</span>
          <span className="r ge-quiet">{decimal(row.max)}</span>
          <span className="r ge-dim">
            {row.to_order > 0 ? decimal(row.to_order) : '—'}
          </span>
          <span className="ge-stock__actions">
            <input
              className="ge-uf__input r"
              style={{ maxWidth: 64 }}
              placeholder={t('page.warehouse.counted')}
              value={counted}
              onChange={(event) => setCounted(event.target.value)}
              onKeyDown={(event) => {
                if (event.key === 'Enter' && counted !== '') {
                  onCount(Number(counted));
                  setCounted('');
                }
              }}
            />
            <button type="button" onClick={onEdit}>
              {t('page.warehouse.thresholds')}
            </button>
          </span>
        </>
      )}
    </div>
  );
}
