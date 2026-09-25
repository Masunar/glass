import CreditOverrideDrawer from '../_components/CreditOverrideDrawer';
import DeadlineDrawer from '../_components/DeadlineDrawer';
import InvestmentDrawer from '../_components/InvestmentDrawer';
import InvoiceDrawer from '../_components/InvoiceDrawer';
import OrderTabs from '../_components/OrderTabs';
import OwnerDrawer from '../_components/OwnerDrawer';
import VatLines from '../_components/VatLines';
import { vatNote } from '../_components/vat';
import { useEffect, useMemo, useRef, useState } from 'react';
import { PiArrowLeft, PiCaretDown, PiWarningCircle } from 'react-icons/pi';
import { Link, useParams } from 'react-router';

import { Button } from '@salvon/components/button';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { notifyError } from '@salvon/utils/notify';

import {
  type NextStep,
  type OrderCard,
  type OrderCardList,
  OrdersApi,
} from '@app/api/OrdersApi';
import HasPermission from '@app/components/HasPermission';
import { Strip, Strips } from '@app/components/list';
import { Permission, SubPermission } from '@app/config/permission';
import { useHasPermission } from '@app/hook/use-permissions';

const money = (value: string | null) =>
  value === null
    ? '—'
    : new Intl.NumberFormat('pl-PL', {
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
  const [investmentOpen, setInvestmentOpen] = useState(false);
  const [ownerOpen, setOwnerOpen] = useState(false);
  const [deadlineOpen, setDeadlineOpen] = useState(false);
  const [invoiceOpen, setInvoiceOpen] = useState(false);
  const [overrideOpen, setOverrideOpen] = useState(false);
  // Termin i komentarze poprawia ten, kto moze zmieniac zlecenie —
  // ta sama regula, ktora pilnuje serwer.
  const canEdit = useHasPermission()(Permission.ORDERS, SubPermission.UPDATE);

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

  /** Cofnięcie zgody — przejście do produkcji znów pilnuje limitu. */
  const revokeOverride = async () => {
    const { response } = await OrdersApi.revokeCreditOverride(id);

    if (!response.success) {
      notifyError(t('api.ise'));

      return;
    }

    await load();
  };

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
  const note = vatNote(card.money, t);
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
          {/* Prowadzacy przed zakladajacym: pierwsze pytanie brzmi
              „kogo pytac o to zlecenie", a nie „kto je wpisal". */}
          <div className="ge-quiet">
            {t('page.orders.owner.label')}{' '}
            {order.owner ?? t('page.orders.card.no_owner')}
            <HasPermission
              permission={Permission.ORDERS}
              sub={SubPermission.UPDATE}
            >
              <Button variant="text" onClick={() => setOwnerOpen(true)}>
                {t('page.orders.owner.change')}
              </Button>
            </HasPermission>
            {order.created_by
              ? ` · ${t('page.orders.card.created_by', { name: order.created_by })}`
              : ''}
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
          noteWarn={note.warn}
          note={note.text}
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
              ? // Bez powodu samo przesuniecie — „— —" w miejscu pustego
                // powodu wygladalo jak blad, a nie jak brak.
                t(
                  order.deadline.shift_reason
                    ? 'page.orders.card.shifted_note'
                    : 'page.orders.card.shifted_note_plain',
                  {
                    date: order.deadline.shifted,
                    reason: order.deadline.shift_reason,
                  },
                )
              : (order.deadline.client ?? t('page.orders.no_deadline'))
          }
          // Szacowany czas to suma dni wpisanych przy etapach
          // najdluzszej formatki. Daty z niego nie wyprowadzamy: dni
          // mowia, ile pracy jest w srodku, nie kiedy hala ja zacznie.
          text={
            <span className="ge-from">
              <span className="ge-from__row">
                <span>{t('page.orders.card.estimated')}</span>
                <span>
                  {order.estimated_days === null
                    ? '—'
                    : t('page.orders.panes.days_value', {
                        count: order.estimated_days,
                      })}
                </span>
              </span>
              <span className="ge-from__note">
                {order.estimated_days === null
                  ? t('page.orders.card.estimated_unknown')
                  : t('page.orders.card.estimated_note')}
              </span>
              {canEdit && (
                <button
                  type="button"
                  className="ge-from__link"
                  onClick={() => setDeadlineOpen(true)}
                >
                  {t('page.orders.deadline.change')}
                </button>
              )}
            </span>
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
                {/* Zgoda mimo limitu stoi na karcie wprost — kto, kiedy,
                    dlaczego. Schowana w dzienniku bylaby zgoda, o ktorej
                    nikt nie wie. */}
                {card.credit.override && (
                  <span className="ge-from__note">
                    {t('page.orders.credit_override.granted', {
                      who: card.credit.override.by ?? '—',
                      when: card.credit.override.at,
                      reason: card.credit.override.reason ?? '—',
                    })}
                  </span>
                )}
                {card.credit.can_override &&
                  card.credit.exceeds_by !== null &&
                  card.credit.override === null && (
                    <button
                      type="button"
                      className="ge-from__link"
                      onClick={() => setOverrideOpen(true)}
                    >
                      {t('page.orders.credit_override.open')}
                    </button>
                  )}
                {card.credit.can_override && card.credit.override && (
                  <button
                    type="button"
                    className="ge-from__link"
                    onClick={() => void revokeOverride()}
                  >
                    {t('page.orders.credit_override.revoke')}
                  </button>
                )}
              </span>
            }
          />
        )}

        {card.tempering && (
          <Strip
            // Szklo poza zakladem to jedyny etap, ktorego zakład nie
            // kontroluje — i jedyny, ktory potrafi zatrzymac zlecenie
            // bez sladu na hali. Pasek ma to powiedziec wprost.
            variant={card.tempering.is_waiting ? 'prod' : 'plain'}
            label={t('page.orders.card.tempering')}
            value={
              card.tempering.is_waiting
                ? t('page.orders.card.tempering_days', {
                    count: card.tempering.days_out ?? 0,
                  })
                : t('page.orders.card.tempering_back')
            }
            noteWarn={card.tempering.broken > 0}
            note={
              card.tempering.broken > 0
                ? t('page.orders.card.tempering_broken', {
                    count: card.tempering.broken,
                  })
                : t('page.orders.card.tempering_note', {
                    sent: card.tempering.sent,
                    queued: card.tempering.queued,
                  })
            }
            text={
              <span className="ge-from">
                {card.tempering.batches.map((batch) => (
                  <span className="ge-from__row" key={batch.id}>
                    <span>
                      {t('page.orders.card.tempering_batch', {
                        number: batch.number,
                      })}{' '}
                      {batch.supplier}
                    </span>
                    <span>{batch.expected_at ?? '—'}</span>
                  </span>
                ))}
                <Link to="/hartownia" className="ge-from__link">
                  {t('page.orders.card.tempering_link')} →
                </Link>
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
              {order.invoice.buyer_name && order.invoice.buyer_tax_id && (
                <div className="ge-quiet">NIP {order.invoice.buyer_tax_id}</div>
              )}
              {canEdit && (
                <Button variant="text" onClick={() => setInvoiceOpen(true)}>
                  {t('page.orders.invoice.edit')}
                </Button>
              )}
              {order.invoice.accounting_note && (
                <div className="ge-quiet">{order.invoice.accounting_note}</div>
              )}

              {/* Inwestycja stoi przy fakturze, bo tylko na nia wplywa.
                  Zdanie mowi, co z metrazu wynika — sam metraz nic nie
                  znaczy, dopoki nie wiadomo, ile z niego idzie na 8 %. */}
              <div className="ge-kv ge-kv--top">
                <span className="ge-kv__k">
                  {t('page.orders.investment.title')}
                </span>
                <span>
                  {order.investment === null
                    ? t('page.orders.investment.none')
                    : `${order.investment.type_label}${
                        order.investment.area_m2 === null
                          ? ''
                          : ` · ${order.investment.area_m2} m²`
                      }`}
                </span>
              </div>
              {order.investment !== null && (
                <div
                  className={
                    order.investment.share === null
                      ? 'ge-note ge-note--warn'
                      : 'ge-quiet'
                  }
                >
                  {order.investment.share === null
                    ? order.investment.reason
                    : order.investment.is_split
                      ? t('page.orders.investment.split', {
                          percent: Math.round(order.investment.share * 100),
                          reduced: order.investment.reduced_rate,
                          standard: order.investment.standard_rate,
                        })
                      : t('page.orders.investment.within', {
                          reduced: order.investment.reduced_rate,
                          limit: order.investment.limit_m2 ?? 0,
                        })}
                </div>
              )}
              <Button variant="text" onClick={() => setInvestmentOpen(true)}>
                {t('page.orders.investment.edit')}
              </Button>
            </div>
          </section>

          <VatLines totals={card.money} t={t} />

          {/* Ostatnia oferta w karcie, a nie tylko w zakladce: pytanie
              „czy cos do niego poszlo i w jakiej formie" pada przy
              otwarciu zlecenia, nie po kliknieciu w historie. */}
          <section className="ge-section">
            <div className="ge-section__head">
              {t('page.orders.offers.title')}
            </div>
            {card.offers === null ? (
              <div className="ge-quiet">{t('page.orders.offers.none')}</div>
            ) : (
              <div className="ge-section__body">
                <div className="ge-kv">
                  <span className="ge-kv__k">{card.offers.last.number}</span>
                  <span
                    className={`ge-offers__state is-${card.offers.last.status}`}
                  >
                    {card.offers.last.status_label}
                  </span>
                </div>
                <div className="ge-quiet">
                  {[
                    t(
                      `page.orders.offers.display_${card.offers.last.price_display}`,
                    ),
                    t(
                      `page.orders.offers.detail_${card.offers.last.detail_level}`,
                    ),
                    card.offers.last.is_variant
                      ? t('page.orders.offers.variant')
                      : null,
                  ]
                    .filter(Boolean)
                    .join(' · ')}
                </div>
                {card.offers.last.is_expired && (
                  <div className="ge-note ge-note--warn">
                    {t('page.orders.offers.expired', {
                      date: card.offers.last.valid_until ?? '',
                    })}
                  </div>
                )}
              </div>
            )}
            <Link to={`/orders/${id}/oferty`} className="ge-from__link">
              {t('page.orders.offers.go')}
            </Link>
          </section>
        </aside>

        <div className="ge-card__main">
          {/* Komentarze w szerokiej kolumnie, nie w bocznej: komentarz dla
              produkcji bywa akapitem, a w polowie waskiego wiersza
              „etykieta — wartosc" lamal sie co dwa slowa. Cztery obok
              siebie, bo kazdy ma innego odbiorce i czyta sie je osobno. */}
          <section className="ge-section ge-notes">
            <div className="ge-section__head">
              {t('page.orders.card.comments')}
            </div>
            <div className="ge-notes__grid">
              <Comment
                label={t('page.orders.card.comment_short')}
                value={order.comments.short}
                field="short"
                orderId={id}
                canEdit={canEdit}
                onSaved={() => void load()}
                t={t}
              />
              <Comment
                label={t('page.orders.card.comment_production')}
                value={order.comments.production}
                field="production"
                orderId={id}
                canEdit={canEdit}
                onSaved={() => void load()}
                t={t}
              />
              <Comment
                label={t('page.orders.card.comment_installer')}
                value={order.comments.installer}
                field="installer"
                orderId={id}
                canEdit={canEdit}
                onSaved={() => void load()}
                t={t}
              />
              <Comment
                label={t('page.orders.card.comment_offer')}
                value={order.comments.offer}
                field="offer"
                orderId={id}
                canEdit={canEdit}
                onSaved={() => void load()}
                t={t}
              />
            </div>
          </section>

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
                    {/* Pusty licznik znaczy „zlecenie nie bylo jeszcze
                        na produkcji", nie „zero zrobione". */}
                    {step.tasks !== null && (
                      <div
                        className={
                          step.problems > 0
                            ? 'ge-path__state ge-path__state--stuck'
                            : 'ge-path__state'
                        }
                      >
                        {t('page.orders.card.path_done', {
                          done: step.done ?? 0,
                          total: step.tasks,
                        })}
                        {step.problems > 0
                          ? ` · ${t('page.orders.card.path_problems', {
                              count: step.problems,
                            })}`
                          : ''}
                      </div>
                    )}
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

      <OwnerDrawer
        orderId={id}
        ownerId={order.owner_id}
        owners={card.owners}
        open={ownerOpen}
        onClose={() => setOwnerOpen(false)}
        onSaved={() => {
          setOwnerOpen(false);
          void load();
        }}
      />

      <InvoiceDrawer
        orderId={id}
        invoice={order.invoice}
        types={card.invoice_types}
        open={invoiceOpen}
        onClose={() => setInvoiceOpen(false)}
        onSaved={() => {
          setInvoiceOpen(false);
          void load();
        }}
      />

      <CreditOverrideDrawer
        orderId={id}
        exceedsBy={
          card.credit?.exceeds_by ? money(card.credit.exceeds_by) : null
        }
        open={overrideOpen}
        onClose={() => setOverrideOpen(false)}
        onSaved={() => {
          setOverrideOpen(false);
          void load();
        }}
      />

      <DeadlineDrawer
        orderId={id}
        deadline={order.deadline}
        open={deadlineOpen}
        onClose={() => setDeadlineOpen(false)}
        onSaved={() => {
          setDeadlineOpen(false);
          void load();
        }}
      />

      <InvestmentDrawer
        orderId={id}
        investment={order.investment}
        open={investmentOpen}
        onClose={() => setInvestmentOpen(false)}
        onSaved={() => {
          setInvestmentOpen(false);
          void load();
        }}
      />
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

/**
 * Komentarz zlecenia — kolumna w pasie komentarzy.
 *
 * Etykieta z „edytuj" w jednej linii, tekst pod nią na całą szerokość
 * kolumny. Długi tekst zwija się do kilku linii z „pokaż całość", żeby
 * jeden akapit dla produkcji nie spychał list pozycji o ekran w dół.
 * Zapis dotyczy tylko tego komentarza — dwie osoby przy różnych polach
 * nie nadpisują sobie pracy.
 */
function Comment({
  label,
  value,
  field,
  orderId,
  canEdit,
  onSaved,
  t,
}: {
  label: string;
  value: string | null;
  field: 'short' | 'production' | 'installer' | 'offer';
  orderId: number;
  canEdit: boolean;
  onSaved: () => void;
  t: (key: string) => string;
}) {
  const [editing, setEditing] = useState(false);
  const [draft, setDraft] = useState('');
  const [saving, setSaving] = useState(false);
  const [problem, setProblem] = useState<string | null>(null);
  const [expanded, setExpanded] = useState(false);
  // Przycisk „pokaz calosc" tylko wtedy, gdy tekst naprawde sie nie
  // miesci — przy dwoch linijkach bylby obietnica, ktorej nie ma czym
  // spelnic.
  const textRef = useRef<HTMLDivElement | null>(null);
  const [overflows, setOverflows] = useState(false);

  useEffect(() => {
    const element = textRef.current;

    setOverflows(
      element !== null && element.scrollHeight > element.clientHeight + 1,
    );
  }, [value, editing]);

  const open = () => {
    setDraft(value ?? '');
    setProblem(null);
    setEditing(true);
  };

  const save = async () => {
    setSaving(true);

    const { content, response } = await OrdersApi.saveComment(
      orderId,
      field,
      draft,
    );

    setSaving(false);

    if (!response.success) {
      const message: string | undefined =
        content?.data?.text?.[0] ?? content?.errors?.text?.[0];

      if (message) {
        setProblem(message);
      } else {
        notifyError(t('api.ise'));
      }

      return;
    }

    setEditing(false);
    onSaved();
  };

  return (
    <div className="ge-note-col">
      <div className="ge-note-col__head">
        <span>{label}</span>
        {canEdit && !editing && (
          <button type="button" className="ge-note-col__edit" onClick={open}>
            {t(
              value === null
                ? 'page.orders.card.comment_add'
                : 'page.orders.card.comment_edit',
            )}
          </button>
        )}
      </div>

      {editing ? (
        <>
          <textarea
            className="ge-uf__input ge-note-col__input"
            value={draft}
            rows={field === 'short' ? 3 : 6}
            maxLength={field === 'short' ? 200 : 2000}
            autoFocus
            onChange={(event) => setDraft(event.target.value)}
            onKeyDown={(event) => {
              if (event.key === 'Escape') {
                setEditing(false);
              }
            }}
          />
          {problem !== null && (
            <div className="ge-note ge-note--warn">{problem}</div>
          )}
          <div className="ge-note-col__actions">
            <Button
              variant="text"
              size="small"
              onClick={() => setEditing(false)}
            >
              {t('cancel')}
            </Button>
            <Button
              variant="contained"
              size="small"
              loading={saving}
              onClick={() => void save()}
            >
              {t('save')}
            </Button>
          </div>
        </>
      ) : value === null ? (
        <div className="ge-muted">{t('page.orders.card.none')}</div>
      ) : (
        <>
          <div
            ref={textRef}
            className={
              expanded ? 'ge-note-col__text' : 'ge-note-col__text is-clamped'
            }
          >
            {value}
          </div>
          {(overflows || expanded) && (
            <button
              type="button"
              className="ge-note-col__edit"
              onClick={() => setExpanded((current) => !current)}
            >
              {t(
                expanded
                  ? 'page.orders.card.comment_less'
                  : 'page.orders.card.comment_more',
              )}
            </button>
          )}
        </>
      )}
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
            {item.pane && item.pane.shape !== 'rectangle'
              ? ` · ${t(`page.orders.shape.${item.pane.shape}`)}`
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
