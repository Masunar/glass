import { useEffect, useMemo, useState } from 'react';
import { PiArrowLeft, PiWarningCircle } from 'react-icons/pi';
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
  const [tab, setTab] = useState<'card' | 'history'>('card');
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
          />
        )}

        <Strip
          variant={primary ? 'plain' : 'alert'}
          wide
          label={t('page.orders.card.next')}
          text={
            <Steps
              steps={card.steps}
              busy={busy}
              reasonFor={reasonFor}
              reason={reason}
              t={t}
              onReasonFor={(step) => {
                setReasonFor(step);
                setReason('');
              }}
              onReason={setReason}
              onRun={(step, value) => void run(step, value)}
            />
          }
        />
      </Strips>

      <nav className="ge-filters" aria-label={t('page.orders.card.sections')}>
        <button
          type="button"
          className={tab === 'card' ? 'is-active' : ''}
          onClick={() => setTab('card')}
        >
          {t('page.orders.card.tab_card')}
        </button>
        <button
          type="button"
          className={tab === 'history' ? 'is-active' : ''}
          onClick={() => setTab('history')}
        >
          {t('page.orders.card.tab_history')} {card.history.length}
        </button>
        {/* Formatki maja wlasny adres — wysyla sie do nich link. */}
        <Link to={`/orders/${id}/formatki`}>
          {t('page.orders.card.tab_panes')}{' '}
          {card.lists.reduce((sum, list) => sum + list.items.length, 0)}
        </Link>
      </nav>

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
          {tab === 'card' ? (
            <>
              <section className="ge-section">
                <div className="ge-section__head ge-section__head--strong">
                  {t('page.orders.card.path')}
                  <span className="ge-section__end ge-quiet">
                    {t('page.orders.card.path_note')}
                  </span>
                </div>
                {card.path.length === 0 ? (
                  <div className="ge-quiet">
                    {t('page.orders.card.no_path')}
                  </div>
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
                          · {money(step.amount)}
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
            </>
          ) : (
            <section className="ge-section">
              <div className="ge-section__head ge-section__head--strong">
                {t('page.orders.card.tab_history')}
              </div>
              {card.history.length === 0 ? (
                <div className="ge-quiet">
                  {t('page.orders.card.no_history')}
                </div>
              ) : (
                <div className="ge-log">
                  {card.history.map((entry, index) => (
                    <div className="ge-log__entry" key={index}>
                      <span className="ge-log__time">{entry.at}</span>
                      <span>
                        {entry.user ?? t('page.orders.card.system')} —{' '}
                        {entry.changes
                          .map(
                            (change) =>
                              `${change.field}: ${String(change.before ?? '—')} → ${String(change.after ?? '—')}`,
                          )
                          .join(', ')}
                      </span>
                    </div>
                  ))}
                </div>
              )}
            </section>
          )}
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
 * Wszystkie przejścia z bieżącego statusu, nie tylko dostępne.
 * Zablokowane niosą powód — ekran, który pokazuje sam brak przycisku,
 * zostawia człowieka z pytaniem „dlaczego nie mogę".
 */
function Steps({
  steps,
  busy,
  reasonFor,
  reason,
  t,
  onReasonFor,
  onReason,
  onRun,
}: {
  steps: NextStep[];
  busy: boolean;
  reasonFor: number | null;
  reason: string;
  t: (key: string, options?: Record<string, unknown>) => string;
  onReasonFor: (id: number | null) => void;
  onReason: (value: string) => void;
  onRun: (step: NextStep, reason?: string) => void;
}) {
  if (steps.length === 0) {
    return <span className="ge-quiet">{t('page.orders.card.no_steps')}</span>;
  }

  return (
    <div className="ge-steps">
      {steps.map((step) => (
        <div className="ge-step" key={step.transition_id}>
          {step.available && (
            <Button
              variant="outlined"
              size="small"
              disabled={busy}
              onClick={() => onRun(step)}
            >
              {step.label}
            </Button>
          )}

          {!step.available && step.needs_reason && (
            <>
              <Button
                variant="outlined"
                size="small"
                disabled={busy}
                onClick={() =>
                  onReasonFor(
                    reasonFor === step.transition_id
                      ? null
                      : step.transition_id,
                  )
                }
              >
                {step.label}
              </Button>
              {reasonFor === step.transition_id && (
                <span className="ge-reason">
                  <input
                    value={reason}
                    placeholder={t('page.orders.card.reason')}
                    aria-label={t('page.orders.card.reason')}
                    onChange={(event) => onReason(event.target.value)}
                  />
                  <Button
                    variant="contained"
                    size="small"
                    disabled={busy || reason.trim() === ''}
                    onClick={() => onRun(step, reason.trim())}
                  >
                    {t('page.orders.card.confirm')}
                  </Button>
                </span>
              )}
            </>
          )}

          {!step.available && !step.needs_reason && (
            <span
              className={
                step.unknown
                  ? 'ge-step__why ge-step__why--wait'
                  : 'ge-step__why'
              }
            >
              <PiWarningCircle
                style={{ verticalAlign: '-2px', marginRight: 4 }}
              />
              {step.label}: {step.blocked_by}
            </span>
          )}
        </div>
      ))}
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
