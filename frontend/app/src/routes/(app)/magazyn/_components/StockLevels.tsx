import { useState } from 'react';

import type { StockBoard, StockRow } from '@app/api/WarehouseApi';

import { decimal } from './decimal';

type Translate = (key: string, options?: Record<string, unknown>) => string;

/**
 * Stany magazynowe: progi, sugestia zakupowa i zaznaczanie pod
 * zamówienie.
 *
 * Zaznaczenie żyje w tej zakładce, a nie w adresie — jest robocze,
 * znika po utworzeniu zamówień i nie ma sensu bez świeżej listy.
 */
export function StockLevels({
  board,
  t,
  query,
  onQuery,
  shortages,
  onShortages,
  selected,
  onToggle,
  onClear,
  onCreateOrders,
  onSaveThresholds,
  onCount,
}: {
  board: StockBoard | null;
  t: Translate;
  query: string;
  onQuery: (value: string) => void;
  shortages: boolean;
  onShortages: (value: boolean) => void;
  selected: number[];
  onToggle: (productId: number) => void;
  onClear: () => void;
  onCreateOrders: () => void;
  onSaveThresholds: (row: StockRow, min: number, max: number) => void;
  onCount: (row: StockRow, counted: number) => void;
}) {
  const [editing, setEditing] = useState<number | null>(null);
  const rows = board?.rows ?? [];

  return (
    <>
      <div className="ge-segbar">
        <input
          className="ge-uf__input"
          style={{ maxWidth: 240 }}
          placeholder={t('page.warehouse.search')}
          value={query}
          onChange={(event) => onQuery(event.target.value)}
        />
        <label className="ge-toggle">
          <input
            type="checkbox"
            checked={shortages}
            onChange={(event) => onShortages(event.target.checked)}
          />
          <span className="ge-toggle__track" />
          {t('page.warehouse.only_shortages')}
        </label>

        {/* Pasek akcji pojawia sie dopiero, gdy jest co zamowic —
            pusty przycisk „Utworz zamowienia (0)" uczy, ze nic nie robi. */}
        {selected.length > 0 && (
          <span className="ge-segbar__end ge-stock__bulk">
            <span className="ge-quiet">
              {t('page.warehouse.selected', { count: selected.length })}
            </span>
            <button type="button" onClick={onClear}>
              {t('page.warehouse.clear_selection')}
            </button>
            <button type="button" className="ge-act--go" onClick={onCreateOrders}>
              {t('page.warehouse.create_orders')}
            </button>
          </span>
        )}
      </div>

      <div className="ge-stock ge-stock--pick">
        <div className="ge-stock__head">
          <span />
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

        {rows.map((row) => (
          <StockLine
            key={row.product_id}
            row={row}
            t={t}
            picked={selected.includes(row.product_id)}
            onPick={() => onToggle(row.product_id)}
            editing={editing === row.product_id}
            onEdit={() => setEditing(row.product_id)}
            onCancel={() => setEditing(null)}
            onSave={(min, max) => {
              setEditing(null);
              onSaveThresholds(row, min, max);
            }}
            onCount={(counted) => onCount(row, counted)}
          />
        ))}

        {rows.length === 0 && (
          <div className="ge-empty">{t('page.warehouse.empty')}</div>
        )}
      </div>
    </>
  );
}

function StockLine({
  row,
  t,
  picked,
  onPick,
  editing,
  onEdit,
  onCancel,
  onSave,
  onCount,
}: {
  row: StockRow;
  t: Translate;
  picked: boolean;
  onPick: () => void;
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
      <span>
        {/* Zaznaczyc da sie tylko to, co ma co zamawiac. Zaznaczenie
            pozycji bez braku nie stworzyloby wiersza zamowienia,
            a czlowiek dowiedzialby sie o tym dopiero z „pominietych". */}
        <input
          type="checkbox"
          checked={picked}
          disabled={row.to_order <= 0}
          onChange={onPick}
          aria-label={row.name}
        />
      </span>
      <span className="ge-quiet">{row.code ?? '—'}</span>
      <span className="ge-cell--wrap">{row.name}</span>
      <span className="ge-quiet">{row.finish ?? '—'}</span>
      <span className={row.quantity < row.min ? 'r ge-note--warn' : 'r'}>
        {decimal(row.quantity)}
      </span>
      <span className="r ge-quiet">
        {row.reserved > 0 ? decimal(row.reserved) : '—'}
      </span>
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
