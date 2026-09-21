import { useState } from 'react';

import type {
  PurchaseOrderBoard,
  PurchaseOrderCard,
  PurchaseOrderRow,
} from '@app/api/WarehouseApi';

import { decimal } from './decimal';

type Translate = (key: string, options?: Record<string, unknown>) => string;

/**
 * Zamówienia do dostawców.
 *
 * Jedna lista z filtrem statusu, a nie trzy zakładki jak w starym
 * systemie. Wiersz rozwija się w kartę z pozycjami i formularzem
 * przyjęcia — przyjęcie jest tym, po co się tu wchodzi, więc nie ma
 * powodu chować go pod osobnym adresem.
 */
export function PurchaseOrders({
  board,
  card,
  t,
  status,
  onStatus,
  onOpen,
  onCreate,
  onSend,
  onCancel,
  onReceive,
}: {
  board: PurchaseOrderBoard | null;
  card: PurchaseOrderCard | null;
  t: Translate;
  status: string;
  onStatus: (value: string) => void;
  onOpen: (id: number | null) => void;
  onCreate: (supplierId: number, expectedAt: string, note: string) => void;
  onSend: (id: number) => void;
  onCancel: (id: number) => void;
  onReceive: (
    id: number,
    lines: { item_id: number; quantity: number; unit_net_price: string }[],
    receivedAt: string,
    documentNo: string,
  ) => void;
}) {
  const [creating, setCreating] = useState(false);
  const rows = board?.rows ?? [];

  return (
    <>
      <div className="ge-segbar">
        <nav className="ge-seg ge-seg--filter" aria-label={t('page.warehouse.tab_orders')}>
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
        <span className="ge-segbar__end">
          <button type="button" onClick={() => setCreating(true)}>
            {t('page.warehouse.new_order')}
          </button>
        </span>
      </div>

      {creating && (
        <NewOrder
          t={t}
          suppliers={board?.suppliers ?? []}
          onCancel={() => setCreating(false)}
          onCreate={(supplierId, expectedAt, note) => {
            setCreating(false);
            onCreate(supplierId, expectedAt, note);
          }}
        />
      )}

      <div className="ge-stock ge-stock--orders">
        <div className="ge-stock__head">
          <span className="r">{t('page.warehouse.column.number')}</span>
          <span>{t('page.warehouse.column.supplier')}</span>
          <span>{t('page.warehouse.column.order_status')}</span>
          <span className="r">{t('page.warehouse.column.ordered_at')}</span>
          <span className="r">{t('page.warehouse.column.expected_at')}</span>
          <span className="r">{t('page.warehouse.column.lines')}</span>
          <span className="r">{t('page.warehouse.column.received_of')}</span>
          <span className="r">{t('page.warehouse.column.net_value')}</span>
          <span />
        </div>

        {rows.map((row) => (
          <OrderLine
            key={row.id}
            row={row}
            t={t}
            open={card?.id === row.id}
            card={card?.id === row.id ? card : null}
            onOpen={() => onOpen(card?.id === row.id ? null : row.id)}
            onSend={() => onSend(row.id)}
            onCancel={() => onCancel(row.id)}
            onReceive={(lines, receivedAt, documentNo) =>
              onReceive(row.id, lines, receivedAt, documentNo)
            }
          />
        ))}

        {rows.length === 0 && (
          <div className="ge-empty">{t('page.warehouse.orders_empty')}</div>
        )}
      </div>
    </>
  );
}

function OrderLine({
  row,
  card,
  t,
  open,
  onOpen,
  onSend,
  onCancel,
  onReceive,
}: {
  row: PurchaseOrderRow;
  card: PurchaseOrderCard | null;
  t: Translate;
  open: boolean;
  onOpen: () => void;
  onSend: () => void;
  onCancel: () => void;
  onReceive: (
    lines: { item_id: number; quantity: number; unit_net_price: string }[],
    receivedAt: string,
    documentNo: string,
  ) => void;
}) {
  return (
    <>
      <div className={open ? 'ge-stock__row is-open' : 'ge-stock__row'}>
        <span className="r ge-dim">{row.number}</span>
        <span className="ge-cell--wrap">{row.supplier}</span>
        <span className={row.is_open ? '' : 'ge-quiet'}>{row.status_label}</span>
        <span className="r ge-quiet">{row.ordered_at ?? '—'}</span>
        <span className="r ge-quiet">{row.expected_at ?? '—'}</span>
        <span className="r ge-quiet">{row.lines}</span>
        <span className="r">
          {decimal(row.quantity_received)} / {decimal(row.quantity_ordered)}
        </span>
        {/* Brak ceny przy choc jednej pozycji unieważnia sume — kwota
            policzona z czesci pozycji klamie bardziej niz jej brak. */}
        <span className="r ge-dim">{row.net_value ?? '—'}</span>
        <span className="ge-stock__actions">
          <button type="button" onClick={onOpen}>
            {open ? t('page.warehouse.collapse') : t('page.warehouse.expand')}
          </button>
        </span>
      </div>

      {open && card !== null && (
        <OrderCard
          card={card}
          t={t}
          onSend={onSend}
          onCancel={onCancel}
          onReceive={onReceive}
        />
      )}
    </>
  );
}

function OrderCard({
  card,
  t,
  onSend,
  onCancel,
  onReceive,
}: {
  card: PurchaseOrderCard;
  t: Translate;
  onSend: () => void;
  onCancel: () => void;
  onReceive: (
    lines: { item_id: number; quantity: number; unit_net_price: string }[],
    receivedAt: string,
    documentNo: string,
  ) => void;
}) {
  // Formularz startuje z tym, co jeszcze ma przyjechac, i z cena
  // z zamowienia. Dostawa zgodna z zamowieniem to jedno klikniecie,
  // a rozbiezna wymaga poprawki — czyli swiadomej decyzji.
  const [quantities, setQuantities] = useState<Record<number, string>>(() =>
    Object.fromEntries(
      card.items.map((item) => [item.id, item.outstanding > 0 ? String(item.outstanding) : '']),
    ),
  );
  const [prices, setPrices] = useState<Record<number, string>>(() =>
    Object.fromEntries(card.items.map((item) => [item.id, item.unit_net_price ?? ''])),
  );
  const [receivedAt, setReceivedAt] = useState('');
  const [documentNo, setDocumentNo] = useState('');

  const lines = card.items
    .map((item) => ({
      item_id: item.id,
      quantity: Number(quantities[item.id] ?? '0'),
      unit_net_price: prices[item.id] ?? '',
    }))
    .filter((line) => Number.isFinite(line.quantity) && line.quantity > 0);

  return (
    <div className="ge-po">
      {card.note !== null && card.note !== '' && (
        <p className="ge-lead">{card.note}</p>
      )}

      <div className="ge-po__grid ge-po__grid--head">
        <span>{t('page.warehouse.column.code')}</span>
        <span>{t('page.warehouse.column.name')}</span>
        <span className="r">{t('page.warehouse.column.ordered')}</span>
        <span className="r">{t('page.warehouse.column.received')}</span>
        <span className="r">{t('page.warehouse.column.outstanding')}</span>
        <span className="r">{t('page.warehouse.column.receive_now')}</span>
        <span className="r">{t('page.warehouse.column.unit_price')}</span>
      </div>

      {card.items.map((item) => (
        <div className="ge-po__grid" key={item.id}>
          <span className="ge-quiet">{item.code ?? '—'}</span>
          <span className="ge-cell--wrap">{item.name}</span>
          <span className="r">{decimal(item.ordered)}</span>
          <span className="r ge-quiet">{decimal(item.received)}</span>
          <span className={item.outstanding > 0 ? 'r' : 'r ge-quiet'}>
            {item.outstanding > 0 ? decimal(item.outstanding) : '—'}
          </span>
          <span className="r">
            {card.is_open ? (
              <input
                className="ge-uf__input r"
                value={quantities[item.id] ?? ''}
                onChange={(event) =>
                  setQuantities((current) => ({ ...current, [item.id]: event.target.value }))
                }
              />
            ) : (
              '—'
            )}
          </span>
          <span className="r">
            {card.is_open ? (
              <input
                className="ge-uf__input r"
                placeholder="—"
                value={prices[item.id] ?? ''}
                onChange={(event) =>
                  setPrices((current) => ({ ...current, [item.id]: event.target.value }))
                }
              />
            ) : (
              (item.unit_net_price ?? '—')
            )}
          </span>
        </div>
      ))}

      {card.receipts.length > 0 && (
        <p className="ge-quiet ge-po__history">
          {t('page.warehouse.receipts_so_far', { count: card.receipts.length })}{' '}
          {card.receipts.map((receipt) => receipt.received_at).join(', ')}
        </p>
      )}

      <div className="ge-po__foot">
        {card.status === 'draft' && (
          <button type="button" className="ge-act--go" onClick={onSend}>
            {t('page.warehouse.send_order')}
          </button>
        )}

        {card.is_open && card.status !== 'draft' && (
          <>
            <label className="ge-po__field">
              {t('page.warehouse.received_at')}
              <input
                type="date"
                className="ge-uf__input"
                value={receivedAt}
                onChange={(event) => setReceivedAt(event.target.value)}
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
              disabled={lines.length === 0}
              onClick={() => onReceive(lines, receivedAt, documentNo)}
            >
              {t('page.warehouse.receive')}
            </button>
          </>
        )}

        {card.is_open && (
          <button type="button" className="ge-po__cancel" onClick={onCancel}>
            {t('page.warehouse.cancel_order')}
          </button>
        )}

        {/* Cena sprzedazy sie przy przyjeciu nie zmienia i trzeba to
            powiedziec wprost — inaczej ktos zalozy, ze cennik sam
            nadazyl, i nie zajrzy w „Rozjazd cennika". */}
        {card.is_open && card.status !== 'draft' && (
          <span className="ge-quiet ge-po__hint">
            {t('page.warehouse.receive_hint')}
          </span>
        )}
      </div>
    </div>
  );
}

function NewOrder({
  t,
  suppliers,
  onCancel,
  onCreate,
}: {
  t: Translate;
  suppliers: { id: number; name: string }[];
  onCancel: () => void;
  onCreate: (supplierId: number, expectedAt: string, note: string) => void;
}) {
  const [supplier, setSupplier] = useState<string>(
    suppliers.length > 0 ? String(suppliers[0].id) : '',
  );
  const [expectedAt, setExpectedAt] = useState('');
  const [note, setNote] = useState('');

  if (suppliers.length === 0) {
    return (
      <p className="ge-lead ge-lead--warn">{t('page.warehouse.no_suppliers')}</p>
    );
  }

  return (
    <div className="ge-po ge-po--new">
      <label className="ge-po__field">
        {t('page.warehouse.supplier')}
        <select
          className="ge-uf__select"
          value={supplier}
          onChange={(event) => setSupplier(event.target.value)}
        >
          {suppliers.map((row) => (
            <option key={row.id} value={row.id}>
              {row.name}
            </option>
          ))}
        </select>
      </label>
      <label className="ge-po__field">
        {t('page.warehouse.expected_at')}
        <input
          type="date"
          className="ge-uf__input"
          value={expectedAt}
          onChange={(event) => setExpectedAt(event.target.value)}
        />
      </label>
      <label className="ge-po__field ge-po__field--wide">
        {t('page.warehouse.note')}
        <input
          className="ge-uf__input"
          value={note}
          onChange={(event) => setNote(event.target.value)}
        />
      </label>
      <div className="ge-po__foot">
        <button
          type="button"
          className="ge-act--go"
          onClick={() => onCreate(Number(supplier), expectedAt, note)}
        >
          {t('page.warehouse.create_order')}
        </button>
        <button type="button" onClick={onCancel}>
          {t('cancel')}
        </button>
      </div>
    </div>
  );
}
