import OrderTabs from '../_components/OrderTabs';
import { useEffect, useMemo, useRef, useState } from 'react';
import { PiArrowLeft, PiCaretDown, PiWarningCircle } from 'react-icons/pi';
import { Link, useParams } from 'react-router';

import { Button } from '@salvon/components/button';
import { useTranslation } from '@salvon/hooks/useTranslation';

import {
  type NextStep,
  type OrderCard,
  type OrderCardList,
  OrdersApi,
} from '@app/api/OrdersApi';
import { Strip, Strips } from '@app/components/list';

const money = (value: string) =>
  new Intl.NumberFormat('pl-PL', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Number(value));

export default function Page() {
  const t = useTranslation();
  const params = useParams();
  const id = Number(params.id);

  const [card, setCard] = useState<OrderCard | null>(null);
  const [menuOpen, setMenuOpen] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [reasonFor, setReasonFor] = useState<number | null>(null);
  const [reason, setReason] = useState('');
  const [busy, setBusy] = useState(false);

  const load = async () => {
    const { content } = await OrdersApi.card(id);
    const data: OrderCard | undefined = content?.data;

    if (data) {
      setCard(data);
    }
  };

  useEffect(() => {
    void load();
  }, [id]);

  const order = card?.order;

  /**
   * Przycisk główny to pierwsze dostępne przejście. Anulowanie nigdy nim
   * nie jest — jest dostępne z każdego statusu, więc jako podpowiedź
   * mówiłoby „następnym krokiem jest anulowanie tego zlecenia".
   */
  const primary = useMemo(
    () =>
      (card?.steps ?? []).find(
        (step) => step.available && step.to_status_code !== 'ANULOWANE',
      ) ?? null,
    [card],
  );

  /** Przejście, które czeka na powód — pasek pod nagłówkiem, nie modal. */
  const reasonStep = useMemo(
    () =>
      (card?.steps ?? []).find((step) => step.transition_id === reasonFor) ??
      null,
    [card, reasonFor],
  );

  const run = async (step: NextStep, withReason?: string) => {
    setBusy(true);
    setError(null);

    const { content, response } = await OrdersApi.transition(
      id,
      step.transition_id,
      withReason,
    );

    setBusy(false);

    if (!response.success) {
      // Warunki sa sprawdzane drugi raz na serwerze, wiec przycisk moze
      // sie nie powiesc mimo tego, ze byl widoczny.
      setError(content?.errors?.transition?.[0] ?? t('api.ise'));

      return;
    }

    setReasonFor(null);
    setReason('');
    await load();
  };

  if (!card || !order) {
    return <div className="ge-empty">{t('page.orders.card.loading')}</div>;
  }

  const due = order.deadline;
  const vat = card.money.vat_rate;
  const paid = card.payment;

  return (
    <>
      <header className="ge-head">
        <div>
          <div className="ge-head__kicker">
            {order.status ?? '—'}
            {order.created_at
              ? ` · ${t('page.orders.card.created')} ${order.created_at}`
              : ''}
          </div>
          <h1 className="ge-head__title">
            #{order.number}{' '}
            <span className="ge-name" style={{ fontSize: 19 }}>
              {order.contractor?.display_name ?? '—'}
            </span>
          </h1>
          <div className="ge-quiet">
            {order.created_by ?? t('page.orders.card.no_owner')}
            {order.is_on_hold
              ? ` · ${t('page.orders.on_hold', { reason: order.hold_reason ?? '' })}`
              : ''}
            {order.has_open_claim ? ` · ${t('page.orders.card.claim')}` : ''}
          </div>
        </div>

        <div className="ge-head__actions">
          <Link to="/orders" className="ge-quiet">
            <PiArrowLeft style={{ verticalAlign: '-2px', marginRight: 4 }} />
            {t('page.orders.card.back')}
          </Link>
          <MoreMenu
            steps={card.steps}
            primary={primary}
            busy={busy}
            open={menuOpen}
            t={t}
            onToggle={() => setMenuOpen((value) => !value)}
            onClose={() => setMenuOpen(false)}
            onPick={(step) => {
              setMenuOpen(false);

              if (step.needs_reason) {
                setReasonFor(step.transition_id);
                setReason('');

                return;
              }

              void run(step);
            }}
          />

          {primary && (
            <Button
              variant="contained"
              disabled={busy}
              onClick={() => void run(primary)}
            >
              {primary.label}
            </Button>
          )}
        </div>
      </header>

      {error && <div className="ge-alert">{error}</div>}

      {reasonStep && (
        <div className="ge-reasonbar">
          <span>{reasonStep.label}</span>
          <input
            autoFocus
            value={reason}
            placeholder={t('page.orders.card.reason')}
            aria-label={t('page.orders.card.reason')}
            onChange={(event) => setReason(event.target.value)}
          />
          <Button
            variant="contained"
            size="small"
            disabled={busy || reason.trim() === ''}
            onClick={() => void run(reasonStep, reason.trim())}
          >
            {t('page.orders.card.confirm')}
          </Button>
          <Button
            variant="text"
            size="small"
            onClick={() => setReasonFor(null)}
          >
            {t('cancel')}
          </Button>
        </div>
      )}

      <Strips>
        <Strip
          variant="money"
          label={t('page.orders.card.value')}
          value={money(card.money.net)}
          noteWarn={vat === null}
          note={
            vat === null
              ? t('page.orders.card.no_invoice_type')
              : t('page.orders.card.value_note', {
                  vat,
                  gross: money(card.money.gross ?? '0'),
                })
          }
          // Kwota bez rozbicia to liczba bez pochodzenia. Sekcje mowia,
          // z czego sie wziela, a rabat pokazuje sie osobno, bo nie jest
          // czescia wyceny pozycji.
          text={
            <span className="ge-from">
              {card.money.sections.map((section) => (
                <span className="ge-from__row" key={section.section}>
                  <span>{t(`page.price_list.section.${section.section}`)}</span>
                  <span>{money(section.base)}</span>
                </span>
              ))}
              {Number(card.money.discount) > 0 && (
                <span className="ge-from__row ge-from__row--off">
                  <span>{t('page.orders.discount.title')}</span>
                  <span>−{money(card.money.discount)}</span>
                </span>
              )}
              <Link to={`/orders/${id}/formatki`} className="ge-from__link">
                {t('page.orders.card.value_where')}
              </Link>
            </span>
          }
        />

        <Strip
          variant="module"
          label={t('page.orders.card.deadline')}
          value={deadlineValue(due.days_left, t)}
          note={
            order.deadline.shifted
              ? t('page.orders.card.shifted_note', {
                  date: order.deadline.shifted,
                  reason: order.deadline.shift_reason ?? '—',
                })
              : (order.deadline.client ?? t('page.orders.no_deadline'))
          }
        />

        <Strip
          // Nadplata to anomalia i zasluguje na czerwony pasek. Samo
          // „jeszcze nie zaplacone" nia nie jest — kazde swieze zlecenie
          // tak wyglada, a pasek alarmowy na wszystkim nie alarmuje.
          variant={
            paid.due !== null && Number(paid.due) < 0 ? 'alert' : 'money'
          }
          label={t('page.orders.card.paid')}
          value={paid.due === null ? '—' : money(paid.due)}
          noteWarn={paid.due === null}
          // Procent tylko od brutto. Liczony od netto pokazywalby
          // splacone wiecej, niz jest — a to jest klamstwo o pieniadzach.
          note={
            paid.due === null
              ? t('page.orders.card.paid_unknown', { paid: money(paid.paid) })
              : Number(paid.paid) === 0
                ? t('page.orders.card.paid_none')
                : Number(paid.due) < 0
                  ? t('page.orders.card.paid_over', {
                      amount: money(String(-Number(paid.due))),
                    })
                  : t('page.orders.card.paid_note', {
                      paid: money(paid.paid),
                      gross: money(card.money.gross ?? '0'),
                      percent: paid.percent ?? 0,
                    })
          }
          text={
            <span className="ge-from">
              <Link to={`/orders/${id}/platnosci`} className="ge-from__link">
                {t('page.orders.payments.count', { count: paid.count })} →
              </Link>
            </span>
          }
        />

        {card.credit && (
          <Strip
            variant={card.credit.exceeds_by ? 'alert' : 'plain'}
            label={t('page.orders.card.credit')}
            value={money(card.credit.limit)}
            noteWarn={card.credit.exceeds_by !== null}
            note={
              card.credit.exceeds_by
                ? t('page.orders.card.credit_over', {
                    amount: money(card.credit.exceeds_by),
                  })
                : t('page.orders.card.credit_note', {
                    days: card.credit.payment_days,
                  })
            }
            // Sam limit nic nie mowi, dopoki nie widac, z czym go
            // porownujemy: dlug kontrahenta ze wszystkich otwartych
            // zlecen, a obok udzial tego jednego.
            text={
              <span className="ge-from">
                <span className="ge-from__row">
                  <span>{t('page.orders.card.credit_outstanding')}</span>
                  <span>{money(card.credit.outstanding)}</span>
                </span>
                <span className="ge-from__row ge-from__row--off">
                  <span>{t('page.orders.card.credit_this_order')}</span>
                  <span>{money(card.credit.order_value)}</span>
                </span>
              </span>
            }
          />
        )}

        <Strip
          variant={primary ? 'plain' : 'alert'}
          wide
          label={t('page.orders.card.next')}
          // Jedno zdanie, nie lista przyciskow: akcja stoi w naglowku,
          // a pasek ma powiedziec, co dalej i czego brakuje. Lista
          // rosnaca z liczba slepych sciezek rozpychala rzad paskow
          // i powtarzala przycisk, ktory juz byl obok.
          text={<NextSentence steps={card.steps} primary={primary} t={t} />}
        />
      </Strips>

      <OrderTabs orderId={id} active="card" counts={card.tabs} />

      <div className="ge-card">
        <aside className="ge-card__side">
          <section className="ge-section">
            <div className="ge-section__head">
              {t('page.orders.card.client')}
            </div>
            <div className="ge-section__body">
              {order.contractor?.name ?? '—'}
              <div className="ge-quiet">
                {order.contractor?.address}
                {order.contractor?.city ? <br /> : null}
                {order.contractor?.city}
                {order.contractor?.tax_id
                  ? ` · NIP ${order.contractor.tax_id}`
                  : ''}
              </div>
              <div className="ge-quiet">
                {order.contractor?.phone}
                {order.contractor?.email ? <br /> : null}
                {order.contractor?.email}
              </div>
              {order.contractor?.contact && (
                <div className="ge-quiet">{order.contractor.contact}</div>
              )}
            </div>
          </section>

          <section className="ge-section">
            <div className="ge-section__head">
              {t('page.orders.card.handover')}
            </div>
            <div className="ge-section__body">
              {t(`page.orders.handover.${order.delivery.method}`)}
              <div className="ge-quiet">
                {order.delivery.place ??
                  order.delivery.address ??
                  t('page.orders.card.no_place')}
              </div>
              {order.delivery.contact && (
                <div className="ge-quiet">{order.delivery.contact}</div>
              )}
            </div>
          </section>

          <section className="ge-section">
            <div className="ge-section__head">
              {t('page.orders.card.invoice')}
            </div>
            <div className="ge-section__body">
              {order.invoice.type ?? t('page.orders.card.no_invoice_type')}
              <div className="ge-quiet">
                {order.invoice.buyer_name ?? t('page.orders.card.buyer_client')}
              </div>
              {order.invoice.accounting_note && (
                <div className="ge-quiet">{order.invoice.accounting_note}</div>
              )}
            </div>
          </section>

          <section className="ge-section">
            <div className="ge-section__head">
              {t('page.orders.card.comments')}
            </div>
            <Comment
              label={t('page.orders.card.comment_short')}
              value={order.comments.short}
              t={t}
            />
            <Comment
              label={t('page.orders.card.comment_production')}
              value={order.comments.production}
              t={t}
            />
            <Comment
              label={t('page.orders.card.comment_installer')}
              value={order.comments.installer}
              t={t}
            />
            <Comment
              label={t('page.orders.card.comment_offer')}
              value={order.comments.offer}
              t={t}
            />
          </section>
        </aside>

        <div className="ge-card__main">
          <section className="ge-section">
            <div className="ge-section__head ge-section__head--strong">
              {t('page.orders.card.path')}
              <span className="ge-section__end ge-quiet">
                {t('page.orders.card.path_note')}
              </span>
            </div>
            {card.path.length === 0 ? (
              <div className="ge-quiet">{t('page.orders.card.no_path')}</div>
            ) : (
              <div className="ge-path">
                {card.path.map((step) => (
                  <div className="ge-path__step" key={step.code}>
                    <div
                      className={
                        step.is_subcontracted
                          ? 'ge-path__rule ge-path__rule--sub'
                          : 'ge-path__rule'
                      }
                    />
                    <div className="ge-path__label">{step.name}</div>
                    <div className="ge-path__meta">
                      {t('page.orders.card.path_items', {
                        count: step.items,
                      })}{' '}
                      · {money(step.amount)} zł
                      {step.is_subcontracted
                        ? ` · ${t('page.orders.card.subcontracted')}`
                        : ''}
                    </div>
                  </div>
                ))}
              </div>
            )}
          </section>

          {card.lists.map((list) => (
            <ListSection key={list.id} list={list} t={t} />
          ))}
        </div>
      </div>
    </>
  );
}

function deadlineValue(
  daysLeft: number | null,
  t: (key: string, options?: Record<string, unknown>) => string,
) {
  if (daysLeft === null) {
    return t('page.orders.no_deadline');
  }

  if (daysLeft === 0) {
    return t('page.orders.today');
  }

  return daysLeft < 0
    ? t('page.orders.overdue_by', { count: -daysLeft })
    : t('page.orders.in_days', { count: daysLeft });
}

function Comment({
  label,
  value,
  t,
}: {
  label: string;
  value: string | null;
  t: (key: string) => string;
}) {
  return (
    <div className="ge-kv" style={{ alignItems: 'flex-start' }}>
      <span className="ge-kv__k">{label}</span>
      <span style={{ textAlign: 'right', maxWidth: '62%' }}>
        {value ?? (
          <span className="ge-muted">{t('page.orders.card.none')}</span>
        )}
      </span>
    </div>
  );
}

/**
 * Co dalej — jedno zdanie.
 *
 * Akcja stoi w nagłówku, a pasek mówi, co się z tym zleceniem dzieje.
 * Wcześniej była tu lista wszystkich przejść z przyciskami: rosła razem
 * z liczbą ślepych ścieżek, rozpychała rząd pasków przy każdym zleceniu
 * inaczej i powtarzała przycisk stojący dwa centymetry wyżej.
 */
function NextSentence({
  steps,
  primary,
  t,
}: {
  steps: NextStep[];
  primary: NextStep | null;
  t: (key: string, options?: Record<string, unknown>) => string;
}) {
  if (primary) {
    const others = steps.filter(
      (step) => step.transition_id !== primary.transition_id,
    ).length;

    return (
      <span>
        {t('page.orders.card.next_is', { label: primary.label })}
        {others > 0 && (
          <span className="ge-quiet"> {t('page.orders.card.next_more')}</span>
        )}
      </span>
    );
  }

  // Zablokowane z powodem, ktory da sie usunac, wyprzedza ten czekajacy
  // na moduł — czlowiek ma dostac rzecz do zrobienia.
  const blocking =
    steps.find(
      (step) => !step.available && !step.unknown && !step.needs_reason,
    ) ??
    steps.find((step) => !step.available && step.unknown) ??
    null;

  if (blocking === null) {
    return <span className="ge-quiet">{t('page.orders.nothing_to_do')}</span>;
  }

  return (
    <span>
      <PiWarningCircle style={{ verticalAlign: '-2px', marginRight: 6 }} />
      {blocking.blocked_by}
      <span className="ge-quiet"> {t('page.orders.card.next_more')}</span>
    </span>
  );
}

/**
 * Pozostałe przejścia — te, które nie są krokiem głównym.
 *
 * Zablokowane zostają widoczne i wyłączone, z powodem: brak pozycji
 * w menu nie odpowiada na pytanie „dlaczego nie mogę".
 */
function MoreMenu({
  steps,
  primary,
  busy,
  open,
  t,
  onToggle,
  onClose,
  onPick,
}: {
  steps: NextStep[];
  primary: NextStep | null;
  busy: boolean;
  open: boolean;
  t: (key: string, options?: Record<string, unknown>) => string;
  onToggle: () => void;
  onClose: () => void;
  onPick: (step: NextStep) => void;
}) {
  const anchor = useRef<HTMLDivElement>(null);
  const rest = steps.filter(
    (step) => step.transition_id !== primary?.transition_id,
  );

  useEffect(() => {
    if (!open) {
      return;
    }

    const outside = (event: MouseEvent) => {
      if (!anchor.current?.contains(event.target as Node)) {
        onClose();
      }
    };

    const escape = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        onClose();
      }
    };

    document.addEventListener('mousedown', outside);
    document.addEventListener('keydown', escape);

    return () => {
      document.removeEventListener('mousedown', outside);
      document.removeEventListener('keydown', escape);
    };
  }, [open, onClose]);

  if (rest.length === 0) {
    return null;
  }

  return (
    <div className="ge-menu" ref={anchor}>
      <Button
        variant="outlined"
        disabled={busy}
        aria-expanded={open}
        onClick={onToggle}
      >
        {t('page.orders.card.more')} <PiCaretDown style={{ marginLeft: 4 }} />
      </Button>

      {open && (
        <div className="ge-menu__list" role="menu">
          {rest.map((step) => {
            const usable = step.available || step.needs_reason;

            return (
              <button
                key={step.transition_id}
                type="button"
                role="menuitem"
                className={
                  step.to_status_code === 'ANULOWANE'
                    ? 'ge-menu__item ge-menu__item--danger'
                    : 'ge-menu__item'
                }
                disabled={!usable || busy}
                onClick={() => onPick(step)}
              >
                <span className="ge-menu__label">{step.label}</span>
                {!step.available && (
                  <span className="ge-menu__why">
                    {step.needs_reason
                      ? t('page.orders.card.needs_reason')
                      : step.blocked_by}
                  </span>
                )}
              </button>
            );
          })}
        </div>
      )}
    </div>
  );
}

function ListSection({
  list,
  t,
}: {
  list: OrderCardList;
  t: (key: string, options?: Record<string, unknown>) => string;
}) {
  const title =
    list.name ?? t('page.orders.card.list', { number: list.number });

  return (
    <section
      className={
        list.is_included
          ? 'ge-section ge-items'
          : 'ge-section ge-items ge-list--off'
      }
    >
      <div className="ge-section__head ge-section__head--strong">
        {title}
        <span className="ge-section__end ge-quiet">
          {list.role === 'alternative'
            ? t('page.orders.card.role_alternative')
            : t('page.orders.card.role_component')}
          {list.is_included ? '' : ` · ${t('page.orders.card.excluded')}`}
          {list.is_on_hold ? ` · ${t('page.orders.card.list_on_hold')}` : ''}
        </span>
      </div>

      <div className="ge-items__head">
        <span>{t('page.orders.card.column.no')}</span>
        <span>{t('page.orders.card.column.item')}</span>
        <span>{t('page.orders.card.column.details')}</span>
        <span className="r">{t('page.orders.card.column.quantity')}</span>
        <span className="r">{t('page.orders.card.column.price')}</span>
        <span className="r">{t('page.orders.card.column.amount')}</span>
      </div>

      {list.items.map((item, index) => (
        <div className="ge-items__row" key={item.id}>
          <span>{index + 1}</span>
          <span>{item.name}</span>
          <span className="ge-quiet">
            {item.pane && (
              <span className="ge-dim">
                {item.pane.width_mm} × {item.pane.height_mm} mm
              </span>
            )}
            {item.pane?.is_irregular_shape
              ? ` · ${t('page.orders.card.irregular')}`
              : ''}
            {item.processes.length > 0 ? ` · ${item.processes.join(', ')}` : ''}
          </span>
          <span className="r">{Number(item.quantity)}</span>
          <span className="r">{money(item.unit_net_price)}</span>
          <span className="r">{money(item.amount)}</span>
        </div>
      ))}

      <div className="ge-items__total">
        <span className="ge-kv__k">{t('page.orders.card.list_net')}</span>
        <span className="ge-items__sum">{money(list.net)}</span>
      </div>

      {list.comment && <div className="ge-quiet">{list.comment}</div>}
    </section>
  );
}
