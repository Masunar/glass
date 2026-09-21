import { useState } from 'react';
import { Link } from 'react-router';

import type {
  TemperingBatchBoard,
  TemperingBatchCard,
  TemperingBatchRow,
} from '@app/api/TemperingApi';

import { decimal } from '../../magazyn/_components/decimal';

type Translate = (key: string, options?: Record<string, unknown>) => string;

const OUTCOMES = ['returned', 'rework', 'broken', 'missing'] as const;

/**
 * Partie u podwykonawcy.
 *
 * Wiersz rozwija się w kartę z pozycjami i losem każdej z nich.
 * Powrót jest per pozycja, bo z jednego wsadu część wraca, część się
 * tłucze, a część wraca do poprawki.
 */
export function Batches({
  board,
  card,
  t,
  status,
  onStatus,
  onOpen,
  onSend,
  onReceive,
  onSettle,
  onCancel,
}: {
  board: TemperingBatchBoard | null;
  card: TemperingBatchCard | null;
  t: Translate;
  status: string;
  onStatus: (value: string) => void;
  onOpen: (id: number | null) => void;
  onSend: (id: number, sentAt: string) => void;
  onReceive: (id: number, outcomes: Record<number, string>, returnedAt: string) => void;
  onSettle: (id: number, netCost: string, document: string) => void;
  onCancel: (id: number) => void;
}) {
  const rows = board?.rows ?? [];

  return (
    <>
      <div className="ge-segbar">
        <nav className="ge-seg ge-seg--filter" aria-label={t('page.tempering.tab_batches')}>
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

      <div className="ge-stock ge-stock--batches">
        <div className="ge-stock__head">
          <span className="r">{t('page.tempering.column.number')}</span>
          <span>{t('page.tempering.column.supplier')}</span>
          <span>{t('page.tempering.column.batch_status')}</span>
          <span className="r">{t('page.tempering.column.sent_at')}</span>
          <span className="r">{t('page.tempering.column.expected_at')}</span>
          <span className="r">{t('page.tempering.column.days_out')}</span>
          <span className="r">{t('page.tempering.column.lines')}</span>
          <span className="r">{t('page.tempering.column.broken')}</span>
          <span className="r">{t('page.tempering.column.cost')}</span>
          <span />
        </div>

        {rows.map((row) => (
          <BatchLine
            key={row.id}
            row={row}
            t={t}
            open={card?.id === row.id}
            card={card?.id === row.id ? card : null}
            onOpen={() => onOpen(card?.id === row.id ? null : row.id)}
            onSend={(sentAt) => onSend(row.id, sentAt)}
            onReceive={(outcomes, returnedAt) => onReceive(row.id, outcomes, returnedAt)}
            onSettle={(cost, document) => onSettle(row.id, cost, document)}
            onCancel={() => onCancel(row.id)}
          />
        ))}

        {rows.length === 0 && (
          <div className="ge-empty">{t('page.tempering.batches_empty')}</div>
        )}
      </div>
    </>
  );
}

function BatchLine({
  row,
  card,
  t,
  open,
  onOpen,
  onSend,
  onReceive,
  onSettle,
  onCancel,
}: {
  row: TemperingBatchRow;
  card: TemperingBatchCard | null;
  t: Translate;
  open: boolean;
  onOpen: () => void;
  onSend: (sentAt: string) => void;
  onReceive: (outcomes: Record<number, string>, returnedAt: string) => void;
  onSettle: (netCost: string, document: string) => void;
  onCancel: () => void;
}) {
  // Partia poza zakladem dluzej niz zapowiadano swieci: to jedyny
  // moment, w ktorym widac, ze podwykonawca sie spoznia.
  const late =
    row.expected_at !== null &&
    row.returned_at === null &&
    row.expected_at < new Date().toISOString().slice(0, 10);

  return (
    <>
      <div className={open ? 'ge-stock__row is-open' : 'ge-stock__row'}>
        <span className="r ge-dim">{row.number}</span>
        <span className="ge-cell--wrap">{row.supplier}</span>
        <span className={row.is_open ? '' : 'ge-quiet'}>{row.status_label}</span>
        <span className="r ge-quiet">{row.sent_at ?? '—'}</span>
        <span className={late ? 'r ge-note--warn' : 'r ge-quiet'}>
          {row.expected_at ?? '—'}
        </span>
        <span className={late ? 'r ge-note--warn' : 'r'}>{row.days_out ?? '—'}</span>
        <span className="r ge-quiet">{row.lines}</span>
        <span className={row.broken > 0 ? 'r ge-note--warn' : 'r ge-quiet'}>
          {row.broken > 0 ? row.broken : '—'}
        </span>
        <span className="r ge-dim">{row.net_cost ?? '—'}</span>
        <span className="ge-stock__actions">
          <button type="button" onClick={onOpen}>
            {open ? t('page.warehouse.collapse') : t('page.warehouse.expand')}
          </button>
        </span>
      </div>

      {open && card !== null && (
        <BatchCard
          card={card}
          t={t}
          onSend={onSend}
          onReceive={onReceive}
          onSettle={onSettle}
          onCancel={onCancel}
        />
      )}
    </>
  );
}

function BatchCard({
  card,
  t,
  onSend,
  onReceive,
  onSettle,
  onCancel,
}: {
  card: TemperingBatchCard;
  t: Translate;
  onSend: (sentAt: string) => void;
  onReceive: (outcomes: Record<number, string>, returnedAt: string) => void;
  onSettle: (netCost: string, document: string) => void;
  onCancel: () => void;
}) {
  // Domyslnie wszystko wraca cale — to najczestszy przypadek, a kazde
  // odstepstwo jest swiadomym wskazaniem, nie przeoczeniem.
  const [outcomes, setOutcomes] = useState<Record<number, string>>(() =>
    Object.fromEntries(card.items.map((item) => [item.id, 'returned'])),
  );
  const [when, setWhen] = useState('');
  const [cost, setCost] = useState(card.net_cost ?? '');
  const [documentNo, setDocumentNo] = useState(card.document ?? '');

  const kg = card.items.reduce((total, item) => total + item.kg, 0);
  const m2 = card.items.reduce((total, item) => total + item.m2, 0);

  return (
    <div className="ge-po">
      {card.note !== null && card.note !== '' && <p className="ge-lead">{card.note}</p>}

      <p className="ge-quiet ge-temp__totals">
        {decimal(Math.round(kg * 100) / 100)} kg · {decimal(Math.round(m2 * 1000) / 1000)} m²
      </p>

      <div className="ge-temp__grid ge-temp__grid--head">
        <span className="r">{t('page.tempering.column.order')}</span>
        <span>{t('page.tempering.column.glass')}</span>
        <span className="r">{t('page.tempering.column.size')}</span>
        <span className="r">{t('page.tempering.column.quantity')}</span>
        <span className="r">{t('page.tempering.column.kg')}</span>
        <span>{t('page.tempering.column.outcome')}</span>
      </div>

      {card.items.map((item) => (
        <div className="ge-temp__grid" key={item.id}>
          <span className="r">
            <Link to={`/orders/${item.order_id}`}>#{item.order_number}</Link>
          </span>
          <span className="ge-cell--wrap">{item.name}</span>
          <span className="r ge-quiet">
            {item.width_mm} × {item.height_mm}
          </span>
          <span className="r">{decimal(item.quantity)}</span>
          <span className="r ge-quiet">{decimal(item.kg)}</span>
          <span>
            {card.status === 'sent' ? (
              <select
                className="ge-uf__select"
                value={outcomes[item.id] ?? 'returned'}
                onChange={(event) =>
                  setOutcomes((current) => ({ ...current, [item.id]: event.target.value }))
                }
              >
                {OUTCOMES.map((code) => (
                  <option key={code} value={code}>
                    {t(`page.tempering.outcome.${code}`)}
                  </option>
                ))}
              </select>
            ) : (
              <span className={item.status === 'broken' ? 'ge-note--warn' : 'ge-quiet'}>
                {item.status_label}
              </span>
            )}
          </span>
        </div>
      ))}

      <div className="ge-po__foot">
        {card.status === 'draft' && (
          <>
            <label className="ge-po__field">
              {t('page.tempering.sent_at')}
              <input
                type="date"
                className="ge-uf__input"
                value={when}
                onChange={(event) => setWhen(event.target.value)}
              />
            </label>
            <button type="button" className="ge-act--go" onClick={() => onSend(when)}>
              {t('page.tempering.send_batch')}
            </button>
            <button type="button" className="ge-po__cancel" onClick={onCancel}>
              {t('page.tempering.cancel_batch')}
            </button>
          </>
        )}

        {card.status === 'sent' && (
          <>
            <label className="ge-po__field">
              {t('page.tempering.returned_at')}
              <input
                type="date"
                className="ge-uf__input"
                value={when}
                onChange={(event) => setWhen(event.target.value)}
              />
            </label>
            <button
              type="button"
              className="ge-act--go"
              onClick={() => onReceive(outcomes, when)}
            >
              {t('page.tempering.receive_batch')}
            </button>
            {/* Stluczka nie konczy sprawy: szklo wraca do kolejki
                i trzeba je zrobic od nowa. Powiedziec to przed
                kliknieciem, nie po. */}
            <span className="ge-quiet ge-po__hint">
              {t('page.tempering.receive_hint')}
            </span>
          </>
        )}

        {card.status === 'returned' && (
          <>
            <label className="ge-po__field">
              {t('page.tempering.net_cost')}
              <input
                className="ge-uf__input r"
                value={cost}
                onChange={(event) => setCost(event.target.value)}
              />
            </label>
            <label className="ge-po__field">
              {t('page.warehouse.document')}
              <input
                className="ge-uf__input"
                value={documentNo}
                onChange={(event) => setDocumentNo(event.target.value)}
              />
            </label>
            <button
              type="button"
              className="ge-act--go"
              onClick={() => onSettle(cost, documentNo)}
            >
              {t('page.tempering.settle')}
            </button>
            <span className="ge-quiet ge-po__hint">
              {t('page.tempering.settle_hint')}
            </span>
          </>
        )}
      </div>
    </div>
  );
}
