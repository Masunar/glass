import DiscountPanel from '../_components/DiscountPanel';
import FittingDrawer from '../_components/FittingDrawer';
import ListDrawer from '../_components/ListDrawer';
import OrderTabs from '../_components/OrderTabs';
import PaneDrawer from '../_components/PaneDrawer';
import ServiceDrawer from '../_components/ServiceDrawer';
import SwapDrawer from '../_components/SwapDrawer';
import VatLines from '../_components/VatLines';
import { vatNote } from '../_components/vat';
import { useEffect, useState } from 'react';
import { PiPlus, PiTrash } from 'react-icons/pi';
import { useParams } from 'react-router';

import { Button } from '@salvon/components/button';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { notifyError } from '@salvon/utils/notify';

import type {
  OrderFittingRow,
  OrderItemsBoard,
  OrderItemsList,
  OrderPaneRow,
} from '@app/api/OrdersApi';
import { OrdersApi } from '@app/api/OrdersApi';
import { Band, Strip } from '@app/components/list';

const money = (value: string | number | null) =>
  value === null
    ? '—'
    : new Intl.NumberFormat('pl-PL', {
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
  const [fitting, setFitting] = useState<OrderFittingRow | null>(null);
  const [fittingOpen, setFittingOpen] = useState(false);
  const [list, setList] = useState<OrderItemsList | null>(null);
  const [listOpen, setListOpen] = useState(false);
  // Zaznaczone formatki — pod „Zamien material". Zbior, a nie lista
  // per lista zlecenia: zamiana moze objac kilka list naraz.
  const [selected, setSelected] = useState<Set<number>>(new Set());
  const [swapOpen, setSwapOpen] = useState(false);

  const load = async () => {
    const { content } = await OrdersApi.items(id);
    const data: OrderItemsBoard | undefined = content?.data;

    if (data) {
      setBoard(data);
      // Usunieta formatka nie moze zostac w zaznaczeniu — zamiana
      // odrzucilaby cala paczke za jedna nieistniejaca pozycje.
      const present = new Set(
        data.lists.flatMap((entry) => entry.glass.map((row) => row.id)),
      );
      setSelected(
        (current) =>
          new Set([...current].filter((itemId) => present.has(itemId))),
      );
    }
  };

  const select = (ids: number[], on: boolean) =>
    setSelected((current) => {
      const next = new Set(current);

      for (const itemId of ids) {
        if (on) {
          next.add(itemId);
        } else {
          next.delete(itemId);
        }
      }

      return next;
    });

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

  /** Przeniesienie nie rusza ceny — nie zmienia się ani materiał, ani wymiar. */
  const move = async (row: OrderPaneRow, listId: number) => {
    const { content, response } = await OrdersApi.moveItem(id, row.id, listId);

    if (!response.success) {
      notifyError(content?.errors?.order_list_id?.[0] ?? t('api.ise'));

      return;
    }

    await load();
  };

  if (!board) {
    return <div className="ge-empty">{t('page.orders.card.loading')}</div>;
  }

  const totals = board.totals;
  const note = vatNote(totals, t);

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
              setList(null);
              setListOpen(true);
            }}
          >
            {t('page.orders.lists.add')}
          </Button>
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
            icon={<PiPlus />}
            onClick={() => {
              setFitting(null);
              setFittingOpen(true);
            }}
          >
            {t('page.orders.panes.add_fitting')}
          </Button>
          {selected.size > 0 && (
            <>
              <Button variant="text" onClick={() => setSelected(new Set())}>
                {t('page.orders.panes.select_clear')}
              </Button>
              <Button variant="outlined" onClick={() => setSwapOpen(true)}>
                {t('page.orders.panes.swap_selected', {
                  count: selected.size,
                })}
              </Button>
            </>
          )}
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

      <OrderTabs orderId={id} active="panes" counts={board.tabs} />

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
              onEditFitting={(row) => {
                setFitting(row);
                setFittingOpen(true);
              }}
              onRemove={(row) => void remove(row)}
              onEditList={() => {
                setList(list);
                setListOpen(true);
              }}
              onMove={(row, target) => void move(row, target)}
              lists={board.lists}
              selected={selected}
              onSelect={select}
            />
          ))}
        </div>

        <aside className="ge-card__side ge-card__side--right">
          <div className="ge-section">
            <Strip
              variant="money"
              label={t('page.orders.panes.total_net')}
              value={money(totals.net)}
              noteWarn={note.warn}
              note={note.text}
            />

            {/* Rabat pokazany osobno, nie wtopiony w kwote — klient
                dostaje go w tej samej postaci na ofercie. */}
            {Number(totals.discount) > 0 && (
              <div className="ge-disc__summary">
                <div className="ge-kv">
                  <span className="ge-kv__k">
                    {t('page.orders.discount.before')}
                  </span>
                  <span>{money(totals.base)}</span>
                </div>
                <div className="ge-kv">
                  <span className="ge-kv__k">
                    {t('page.orders.discount.title')}
                  </span>
                  <span className="ge-disc__value">
                    −{money(totals.discount)}
                  </span>
                </div>
              </div>
            )}
          </div>

          <VatLines totals={totals} t={t} />

          <DiscountPanel
            orderId={id}
            rows={board.discounts}
            onSaved={() => void load()}
          />

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

          <section className="ge-section">
            <div className="ge-section__head">
              {t('page.orders.panes.days')}
            </div>
            {/* Dni biora sie wylacznie z tego, co ktos wpisal przy
                etapach. Brak wpisow to brak liczby, nie zero. */}
            {totals.days === null ? (
              <div className="ge-quiet">
                {t('page.orders.panes.days_unknown')}
              </div>
            ) : (
              <>
                <div className="ge-kv">
                  <span className="ge-kv__k">
                    {t('page.orders.panes.days_value', {
                      count: totals.days,
                    })}
                  </span>
                </div>
                <div className="ge-quiet">
                  {t('page.orders.panes.days_note')}
                </div>
              </>
            )}
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

      <SwapDrawer
        orderId={id}
        board={board}
        itemIds={[...selected]}
        open={swapOpen}
        onClose={() => setSwapOpen(false)}
        onSaved={() => {
          setSwapOpen(false);
          setSelected(new Set());
          void load();
        }}
      />

      <ListDrawer
        orderId={id}
        list={list}
        vat={board.vat}
        removable={board.lists.length > 1}
        open={listOpen}
        onClose={() => setListOpen(false)}
        onSaved={() => {
          setListOpen(false);
          void load();
        }}
      />

      <FittingDrawer
        orderId={id}
        board={board}
        item={fitting}
        open={fittingOpen}
        onClose={() => setFittingOpen(false)}
        onSaved={() => {
          setFittingOpen(false);
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
  lists,
  t,
  onEditPane,
  onEditService,
  onEditFitting,
  onRemove,
  onEditList,
  onMove,
  selected,
  onSelect,
}: {
  list: OrderItemsList;
  lists: OrderItemsList[];
  t: (key: string, options?: Record<string, unknown>) => string;
  onEditPane: (row: OrderPaneRow) => void;
  onEditService: (row: OrderPaneRow) => void;
  onEditFitting: (row: OrderFittingRow) => void;
  onRemove: (row: OrderPaneRow) => void;
  onEditList: () => void;
  onMove: (row: OrderPaneRow, listId: number) => void;
  selected: Set<number>;
  onSelect: (ids: number[], on: boolean) => void;
}) {
  const title =
    list.name ?? t('page.orders.card.list', { number: list.number });
  const [shown, setShown] = useState<number | null>(null);
  // Zwiniecie pamieta przegladarka, per lista: przy kilkunastu listach
  // czlowiek zwija gotowe i pracuje na jednej — po odswiezeniu ma zastac
  // ten sam widok. Pamiec moze byc niedostepna (tryb prywatny) — wtedy
  // lista jest po prostu rozwinieta.
  const storageKey = `ge.list.collapsed.${list.id}`;
  const [collapsed, setCollapsed] = useState<boolean>(() => {
    try {
      return window.localStorage.getItem(storageKey) === '1';
    } catch {
      return false;
    }
  });

  const toggle = () => {
    setCollapsed((value) => {
      try {
        if (value) {
          window.localStorage.removeItem(storageKey);
        } else {
          window.localStorage.setItem(storageKey, '1');
        }
      } catch {
        // Bez pamieci zwiniecie trwa do odswiezenia strony.
      }

      return !value;
    });
  };

  return (
    <div className={list.is_included ? undefined : 'ge-list--off'}>
      <Band
        variant={
          list.is_on_hold ? 'alert' : list.is_included ? 'module' : 'plain'
        }
        title={
          list.role === 'alternative'
            ? `${title} · ${t('page.orders.card.role_alternative')}`
            : title
        }
        meta={
          collapsed
            ? `${money(list.net)} zł · ${t(
                'page.orders.lists.collapsed_count',
                {
                  count:
                    list.glass.length +
                    list.fittings.length +
                    list.services.length,
                },
              )}`
            : `${money(list.net)} zł`
        }
        collapsed={collapsed}
        onToggle={toggle}
        // Trzy rozne stany, kazdy znaczy co innego: wstrzymana nie
        // pojdzie na produkcje, wylaczona nie nalezy do zlecenia.
        end={
          <span className="ge-band__end">
            {list.is_on_hold && (
              <span className="ge-band__flag">
                {t('page.orders.card.list_on_hold')}
              </span>
            )}
            {!list.is_included && (
              <span className="ge-band__flag">
                {t('page.orders.card.excluded')}
              </span>
            )}
            <button
              type="button"
              className="ge-band__edit"
              onClick={onEditList}
            >
              {t('page.orders.lists.edit_short')}
            </button>
          </span>
        }
      />

      {!collapsed && list.comment !== null && (
        <div className="ge-list__comment">{list.comment}</div>
      )}

      {!collapsed && (
        <div
          className={
            lists.length > 1 ? 'ge-panes ge-panes--movable' : 'ge-panes'
          }
        >
          <div className="ge-panes__head">
            <label className="ge-pick">
              <input
                type="checkbox"
                aria-label={t('page.orders.panes.select_all')}
                disabled={list.glass.length === 0}
                checked={
                  list.glass.length > 0 &&
                  list.glass.every((row) => selected.has(row.id))
                }
                onChange={(event) =>
                  onSelect(
                    list.glass.map((row) => row.id),
                    event.target.checked,
                  )
                }
              />
              {t('page.orders.card.column.no')}
            </label>
            <span>{t('page.orders.panes.column.kind')}</span>
            <span>{t('page.orders.panes.column.material')}</span>
            <span className="r">{t('page.orders.panes.column.width')}</span>
            <span className="r">{t('page.orders.panes.column.height')}</span>
            <span className="r">{t('page.orders.panes.column.count')}</span>
            <span>{t('page.orders.panes.column.processes')}</span>
            <span className="r">{t('page.orders.panes.column.area')}</span>
            <span className="r">
              {t('page.orders.panes.column.unit_price')}
            </span>
            <span className="r">{t('page.orders.card.column.amount')}</span>
            <span />
          </div>

          {list.glass.length === 0 && (
            <div className="ge-empty">{t('page.orders.panes.no_glass')}</div>
          )}

          {list.glass.map((row, index) => (
            <div
              className={
                row.is_urgent
                  ? 'ge-panes__row ge-panes__row--urgent'
                  : 'ge-panes__row'
              }
              key={row.id}
            >
              <label className="ge-pick">
                <input
                  type="checkbox"
                  aria-label={t('page.orders.panes.select')}
                  checked={selected.has(row.id)}
                  onChange={(event) => onSelect([row.id], event.target.checked)}
                />
                {index + 1}
              </label>
              <span>{row.group ?? '—'}</span>
              {/* Pilna formatka ma byc widoczna z listy, a nie dopiero po
                otwarciu panelu — to ona ustawia kolejnosc na hali. */}
              <span className="ge-cell--wrap">
                {row.is_urgent && (
                  <span className="ge-tag ge-tag--urgent">
                    {t('page.orders.panes.urgent')}
                  </span>
                )}
                {row.name}
              </span>
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
              {/* Cena materialu za m2 stoi obok kwoty, bo to z niej ta
                kwota wyrasta. Brak pozycji w cenniku widac tutaj, a nie
                dopiero po tym, ze suma wyszla mniejsza, niz powinna. */}
              <span className="r ge-quiet">{money(row.unit_net_price)}</span>
              <span className="r ge-dim">
                {/* Kwota bez sladu to liczba bez pochodzenia — a tu naklada
                  sie cennik, minimalna powierzchnia, doplaty i procesy. */}
                <button
                  type="button"
                  className="ge-amount"
                  aria-expanded={shown === row.id}
                  onClick={() => setShown(shown === row.id ? null : row.id)}
                >
                  {money(row.total)}
                </button>
                {row.unit_net_price === null && (
                  <div className="ge-note ge-note--warn">
                    {t('page.orders.panes.glass_missing')}
                  </div>
                )}
              </span>
              <span className="ge-panes__actions">
                {/* Przeniesienie bez otwierania panelu: przy dzieleniu
                  wyceny na pomieszczenia robi sie to kilkanascie razy
                  z rzedu. */}
                {lists.length > 1 && (
                  <select
                    className="ge-panes__move"
                    value={list.id}
                    aria-label={t('page.orders.lists.move')}
                    onChange={(event) =>
                      onMove(row, Number(event.target.value))
                    }
                  >
                    {lists.map((target) => (
                      <option key={target.id} value={target.id}>
                        {target.name ??
                          t('page.orders.card.list', { number: target.number })}
                      </option>
                    ))}
                  </select>
                )}
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

              {shown === row.id && (
                <div className="ge-path__trace">
                  <div className="ge-path__trace-head">
                    {t('page.orders.panes.section.path')}
                  </div>
                  {row.price_path.length === 0 ? (
                    <div className="ge-quiet">
                      {t('page.orders.panes.no_trace')}
                    </div>
                  ) : (
                    row.price_path.map((step, position) => (
                      <div className="ge-kv" key={position}>
                        <span className="ge-kv__k">
                          {step.label}
                          {step.detail && (
                            <span className="ge-quiet"> — {step.detail}</span>
                          )}
                        </span>
                        <span className="ge-dim">{step.value}</span>
                      </div>
                    ))
                  )}
                  {row.processes.length > 0 && (
                    <div className="ge-kv">
                      <span className="ge-kv__k">
                        {t('page.orders.panes.column.processes')}
                      </span>
                      <span className="ge-dim">
                        {money(
                          row.processes.reduce(
                            (sum, entry) => sum + Number(entry.amount),
                            0,
                          ),
                        )}
                      </span>
                    </div>
                  )}
                </div>
              )}
            </div>
          ))}

          {list.fittings.length > 0 && (
            <>
              <div className="ge-panes__sub">
                {t('page.orders.panes.fittings')}
              </div>
              {/* Okucia maja wlasne kolumny: nie maja wymiarow, procesow
                ani dni, a maja kod, wykonczenie i stan magazynowy. */}
              <div className="ge-panes__row ge-panes__row--fitting ge-panes__head--sub">
                <span>{t('page.orders.card.column.no')}</span>
                <span>{t('page.orders.panes.column.code')}</span>
                <span>{t('page.orders.panes.column.material')}</span>
                <span>{t('page.orders.panes.column.finish')}</span>
                <span className="r">{t('page.orders.panes.column.count')}</span>
                <span className="r">{t('page.orders.panes.column.stock')}</span>
                <span className="r">
                  {t('page.orders.panes.column.unit_price')}
                </span>
                <span className="r">{t('page.orders.card.column.amount')}</span>
                <span />
              </div>
              {list.fittings.map((row, index) => (
                <div
                  className="ge-panes__row ge-panes__row--fitting"
                  key={row.id}
                >
                  <span>{index + 1}</span>
                  <span className="ge-quiet">{row.code ?? '—'}</span>
                  <span className="ge-cell--wrap">{row.name}</span>
                  <span className="ge-quiet">{row.finish ?? '—'}</span>
                  <span className="r">{Number(row.quantity)}</span>
                  {/* Stan nizszy niz ilosc na zleceniu blokuje wejscie na
                    produkcje, wiec ma byc widac tutaj, a nie dopiero
                    przy zablokowanym przejsciu. */}
                  <span
                    className={
                      row.in_stock !== null &&
                      row.in_stock < Number(row.quantity)
                        ? 'r ge-note--warn'
                        : 'r ge-quiet'
                    }
                  >
                    {row.in_stock === null ? '—' : decimal(row.in_stock, 0)}
                  </span>
                  <span className="r">{money(row.unit_net_price)}</span>
                  <span className="r ge-dim">{money(row.amount)}</span>
                  <span className="ge-panes__actions">
                    <button type="button" onClick={() => onEditFitting(row)}>
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
      )}
    </div>
  );
}
