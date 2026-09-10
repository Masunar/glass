import PaneDrawer from '../_components/PaneDrawer';
import ServiceDrawer from '../_components/ServiceDrawer';
import { useEffect, useState } from 'react';
import { PiPlus, PiTrash } from 'react-icons/pi';
import { Link, useParams } from 'react-router';

import { Button } from '@salvon/components/button';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { notifyError } from '@salvon/utils/notify';

import type {
  OrderItemsBoard,
  OrderItemsList,
  OrderPaneRow,
} from '@app/api/OrdersApi';
import { OrdersApi } from '@app/api/OrdersApi';
import { Band, Strip } from '@app/components/list';

const money = (value: string | number) =>
  new Intl.NumberFormat('pl-PL', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Number(value));

const decimal = (value: number | null, digits = 2) =>
  value === null
    ? '—'
    : new Intl.NumberFormat('pl-PL', {
        minimumFractionDigits: digits,
        maximumFractionDigits: digits,
      }).format(value);

export default function Page() {
  const t = useTranslation();
  const params = useParams();
  const id = Number(params.id);

  const [board, setBoard] = useState<OrderItemsBoard | null>(null);
  const [pane, setPane] = useState<OrderPaneRow | null>(null);
  const [paneOpen, setPaneOpen] = useState(false);
  const [service, setService] = useState<OrderPaneRow | null>(null);
  const [serviceOpen, setServiceOpen] = useState(false);

  const load = async () => {
    const { content } = await OrdersApi.items(id);
    const data: OrderItemsBoard | undefined = content?.data;

    if (data) {
      setBoard(data);
    }
  };

  useEffect(() => {
    void load();
  }, [id]);

  const remove = async (row: OrderPaneRow) => {
    const { response } = await OrdersApi.deleteItem(id, row.id);

    if (!response.success) {
      notifyError(t('api.ise'));

      return;
    }

    await load();
  };

  if (!board) {
    return <div className="ge-empty">{t('page.orders.card.loading')}</div>;
  }

  const panes = board.lists.reduce((sum, list) => sum + list.glass.length, 0);
  const totals = board.totals;

  return (
    <>
      <header className="ge-head">
        <div>
          <div className="ge-head__kicker">
            {t('page.orders.panes.title')} · #{board.order.number}
          </div>
          <h1 className="ge-head__title">{board.order.contractor ?? '—'}</h1>
        </div>

        <div className="ge-head__actions">
          <Button
            variant="outlined"
            onClick={() => {
              setService(null);
              setServiceOpen(true);
            }}
          >
            {t('page.orders.panes.add_service')}
          </Button>
          <Button
            variant="contained"
            icon={<PiPlus />}
            onClick={() => {
              setPane(null);
              setPaneOpen(true);
            }}
          >
            {t('page.orders.panes.add')}
          </Button>
        </div>
      </header>

      <nav className="ge-filters" aria-label={t('page.orders.card.sections')}>
        <Link to={`/orders/${id}`}>{t('page.orders.card.tab_card')}</Link>
        <span className="ge-filters__here">
          {t('page.orders.card.tab_panes')} {panes}
        </span>
      </nav>

      <div className="ge-card">
        <div className="ge-card__main">
          {board.lists.map((list) => (
            <ListBlock
              key={list.id}
              list={list}
              t={t}
              onEditPane={(row) => {
                setPane(row);
                setPaneOpen(true);
              }}
              onEditService={(row) => {
                setService(row);
                setServiceOpen(true);
              }}
              onRemove={(row) => void remove(row)}
            />
          ))}
        </div>

        <aside className="ge-card__side ge-card__side--right">
          <div className="ge-section">
            <Strip
              variant="money"
              label={t('page.orders.panes.total_net')}
              value={money(totals.net)}
              noteWarn={totals.vat_rate === null}
              note={
                totals.gross === null
                  ? t('page.orders.card.no_invoice_type')
                  : t('page.orders.card.value_note', {
                      vat: totals.vat_rate,
                      gross: money(totals.gross),
                    })
              }
            />
          </div>

          <section className="ge-section">
            <div className="ge-section__head">
              {t('page.orders.panes.glass_summary')}
            </div>
            <div className="ge-kv">
              <span className="ge-kv__k">{t('page.orders.panes.area')}</span>
              <span>{decimal(totals.m2)} m²</span>
            </div>
            <div className="ge-kv">
              <span className="ge-kv__k">
                {t('page.orders.panes.perimeter')}
              </span>
              <span>{decimal(totals.mb)} mb</span>
            </div>
            <div className="ge-kv">
              <span className="ge-kv__k">{t('page.orders.panes.weight')}</span>
              <span>{decimal(totals.kg)} kg</span>
            </div>
            <div className="ge-quiet">{t('page.orders.panes.sums_note')}</div>
          </section>
        </aside>
      </div>

      <PaneDrawer
        orderId={id}
        board={board}
        item={pane}
        open={paneOpen}
        onClose={() => setPaneOpen(false)}
        onSaved={() => {
          setPaneOpen(false);
          void load();
        }}
      />

      <ServiceDrawer
        orderId={id}
        board={board}
        item={service}
        open={serviceOpen}
        onClose={() => setServiceOpen(false)}
        onSaved={() => {
          setServiceOpen(false);
          void load();
        }}
      />
    </>
  );
}

function ListBlock({
  list,
  t,
  onEditPane,
  onEditService,
  onRemove,
}: {
  list: OrderItemsList;
  t: (key: string, options?: Record<string, unknown>) => string;
  onEditPane: (row: OrderPaneRow) => void;
  onEditService: (row: OrderPaneRow) => void;
  onRemove: (row: OrderPaneRow) => void;
}) {
  const title =
    list.name ?? t('page.orders.card.list', { number: list.number });

  return (
    <div className={list.is_included ? undefined : 'ge-list--off'}>
      <Band
        variant={list.is_included ? 'module' : 'plain'}
        title={title}
        meta={`${money(list.net)} zł`}
        end={list.is_included ? undefined : t('page.orders.card.excluded')}
      />

      <div className="ge-panes">
        <div className="ge-panes__head">
          <span>{t('page.orders.card.column.no')}</span>
          <span>{t('page.orders.panes.column.kind')}</span>
          <span>{t('page.orders.panes.column.material')}</span>
          <span className="r">{t('page.orders.panes.column.width')}</span>
          <span className="r">{t('page.orders.panes.column.height')}</span>
          <span className="r">{t('page.orders.panes.column.count')}</span>
          <span>{t('page.orders.panes.column.processes')}</span>
          <span className="r">{t('page.orders.panes.column.area')}</span>
          <span className="r">{t('page.orders.card.column.amount')}</span>
          <span />
        </div>

        {list.glass.length === 0 && (
          <div className="ge-empty">{t('page.orders.panes.no_glass')}</div>
        )}

        {list.glass.map((row, index) => (
          <div className="ge-panes__row" key={row.id}>
            <span>{index + 1}</span>
            <span>{row.group ?? '—'}</span>
            <span className="ge-cell--wrap">{row.name}</span>
            <span className="r ge-dim">{row.width_mm}</span>
            <span className="r ge-dim">{row.height_mm}</span>
            <span className="r">{Number(row.quantity)}</span>
            <span className="ge-procs">
              {row.processes.length === 0
                ? '—'
                : row.processes.map((entry) => entry.code).join(' ')}
            </span>
            <span className="r ge-quiet">
              {decimal(row.m2, 2)} / {decimal(row.mb, 2)}
            </span>
            <span className="r ge-dim">
              {money(row.total)}
              {Number(row.total) === 0 && (
                <div className="ge-note ge-note--warn">
                  {t('page.orders.panes.no_price')}
                </div>
              )}
            </span>
            <span className="ge-panes__actions">
              <button type="button" onClick={() => onEditPane(row)}>
                {t('edit')}
              </button>
              <button
                type="button"
                aria-label={t('delete')}
                onClick={() => onRemove(row)}
              >
                <PiTrash />
              </button>
            </span>
          </div>
        ))}

        {list.services.length > 0 && (
          <>
            <div className="ge-panes__sub">
              {t('page.orders.panes.services')}
            </div>
            {list.services.map((row, index) => (
              <div
                className="ge-panes__row ge-panes__row--service"
                key={row.id}
              >
                <span>{index + 1}</span>
                <span className="ge-cell--wrap">{row.name}</span>
                <span className="r">{Number(row.quantity)}</span>
                <span className="r">{money(row.unit_net_price)}</span>
                <span className="r ge-dim">{money(row.amount)}</span>
                <span className="ge-panes__actions">
                  <button type="button" onClick={() => onEditService(row)}>
                    {t('edit')}
                  </button>
                  <button
                    type="button"
                    aria-label={t('delete')}
                    onClick={() => onRemove(row)}
                  >
                    <PiTrash />
                  </button>
                </span>
              </div>
            ))}
          </>
        )}
      </div>
    </div>
  );
}
