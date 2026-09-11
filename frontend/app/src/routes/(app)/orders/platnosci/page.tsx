import OrderTabs from '../_components/OrderTabs';
import { useEffect, useState } from 'react';
import { PiArrowUUpLeft } from 'react-icons/pi';
import { useParams } from 'react-router';

import { Button } from '@salvon/components/button';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { notifyError, notifySuccess } from '@salvon/utils/notify';

import type { OrderPaymentRow, OrderPaymentsBoard } from '@app/api/OrdersApi';
import { OrdersApi } from '@app/api/OrdersApi';

const money = (value: string) =>
  new Intl.NumberFormat('pl-PL', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Number(value));

const today = () => new Date().toISOString().slice(0, 10);

/**
 * Wpłaty do zlecenia.
 *
 * Ekran jest rejestrem, nie listą do kasowania: wpłaty się nie usuwa,
 * koryguje się ją wierszem odwrotnym. Stąd brak kosza przy wierszu
 * i przycisk „Koryguj", który pyta o powód.
 */
export default function Page() {
  const t = useTranslation();
  const params = useParams();
  const id = Number(params.id);

  const [board, setBoard] = useState<OrderPaymentsBoard | null>(null);
  const [registerId, setRegisterId] = useState<string>('');
  const [amount, setAmount] = useState('');
  const [paidOn, setPaidOn] = useState(today());
  const [rate, setRate] = useState('');
  const [note, setNote] = useState('');
  const [busy, setBusy] = useState(false);
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [reverseId, setReverseId] = useState<number | null>(null);
  const [reverseNote, setReverseNote] = useState('');

  const load = async () => {
    const { content } = await OrdersApi.payments(id);
    const data: OrderPaymentsBoard | undefined = content?.data;

    if (data) {
      setBoard(data);

      if (registerId === '' && data.registers.length > 0) {
        setRegisterId(String(data.registers[0].id));
      }
    }
  };

  useEffect(() => {
    void load();
  }, [id]);

  if (!board) {
    return <div className="ge-empty">{t('page.orders.card.loading')}</div>;
  }

  const register =
    board.registers.find((row) => String(row.id) === registerId) ?? null;

  const save = async () => {
    setBusy(true);
    setErrors({});

    const { content, response } = await OrdersApi.addPayment(id, {
      cash_register_id: registerId,
      amount,
      paid_on: paidOn,
      exchange_rate: rate === '' ? null : rate,
      note,
    });

    setBusy(false);

    if (!response.success) {
      setErrors(content?.errors ?? {});
      notifyError(content?.errors?.amount?.[0] ?? t('api.ise'));

      return;
    }

    setAmount('');
    setRate('');
    setNote('');
    notifySuccess(t('api.save_success'));
    await load();
  };

  const reverse = async (row: OrderPaymentRow) => {
    setBusy(true);
    const { content, response } = await OrdersApi.reversePayment(
      id,
      row.id,
      reverseNote,
    );
    setBusy(false);

    if (!response.success) {
      notifyError(content?.errors?.payment?.[0] ?? t('api.ise'));

      return;
    }

    setReverseId(null);
    setReverseNote('');
    await load();
  };

  const summary = board.summary;
  const over = summary.due !== null && Number(summary.due) < 0;

  return (
    <>
      <header className="ge-head">
        <div>
          <div className="ge-head__kicker">
            {t('page.orders.payments.title')} · #{board.order.number}
          </div>
          <h1 className="ge-head__title">
            {t('page.orders.payments.count', {
              count: board.payments.length,
            })}
          </h1>
          <div className="ge-quiet">{board.order.contractor ?? '—'}</div>
        </div>
      </header>

      <OrderTabs orderId={id} active="payments" counts={board.tabs} />

      <div className="ge-card">
        <div className="ge-card__main">
          <section className="ge-section">
            <div className="ge-section__head ge-section__head--strong">
              {t('page.orders.payments.add')}
            </div>

            <div className="ge-upload__meta">
              <label className="ge-uf">
                <span className="ge-uf__label">
                  {t('page.orders.payments.register')}
                </span>
                <select
                  className="ge-uf__input ge-uf__select"
                  value={registerId}
                  onChange={(event) => setRegisterId(event.target.value)}
                >
                  {board.registers.map((row) => (
                    <option key={row.id} value={row.id}>
                      {row.name} · {row.currency}
                    </option>
                  ))}
                </select>
                <span className="ge-uf__hint">
                  {t('page.orders.payments.register_hint')}
                </span>
              </label>

              <label className="ge-uf">
                <span className="ge-uf__label">
                  {t('page.orders.payments.amount')}
                  {register ? ` (${register.currency})` : ''}
                </span>
                <input
                  className="ge-uf__input"
                  inputMode="decimal"
                  value={amount}
                  onChange={(event) => setAmount(event.target.value)}
                />
                {errors.amount && (
                  <span className="ge-uf__error">{errors.amount[0]}</span>
                )}
              </label>

              <label className="ge-uf">
                <span className="ge-uf__label">
                  {t('page.orders.payments.paid_on')}
                </span>
                <input
                  className="ge-uf__input"
                  type="date"
                  value={paidOn}
                  onChange={(event) => setPaidOn(event.target.value)}
                />
              </label>

              {/* Kurs tylko dla kasy w innej walucie — w PLN pole bez
                  sensu, a puste pole zawsze widoczne uczy je pomijac. */}
              {register?.needs_rate && (
                <label className="ge-uf">
                  <span className="ge-uf__label">
                    {t('page.orders.payments.rate', {
                      currency: board.base_currency,
                    })}
                  </span>
                  <input
                    className="ge-uf__input"
                    inputMode="decimal"
                    value={rate}
                    onChange={(event) => setRate(event.target.value)}
                  />
                  <span className="ge-uf__hint">
                    {t('page.orders.payments.rate_hint')}
                  </span>
                  {errors.exchange_rate && (
                    <span className="ge-uf__error">
                      {errors.exchange_rate[0]}
                    </span>
                  )}
                </label>
              )}

              <label className="ge-uf">
                <span className="ge-uf__label">
                  {t('page.orders.payments.note')}
                </span>
                <input
                  className="ge-uf__input"
                  value={note}
                  placeholder={t('page.orders.payments.note_hint')}
                  onChange={(event) => setNote(event.target.value)}
                />
              </label>
            </div>

            <Button
              variant="contained"
              size="small"
              disabled={busy}
              onClick={() => void save()}
            >
              {t('page.orders.payments.save')}
            </Button>
          </section>

          <section className="ge-section">
            <div className="ge-section__head ge-section__head--strong">
              {t('page.orders.payments.list')}
            </div>

            {board.payments.length === 0 && (
              <div className="ge-quiet">{t('page.orders.payments.empty')}</div>
            )}

            {board.payments.map((row) => (
              <div className="ge-pay" key={row.id}>
                <span className="ge-pay__date">{row.paid_on}</span>

                <span className="ge-pay__name">
                  <span>
                    {row.register ?? '—'}
                    {row.is_reversal
                      ? ` · ${t('page.orders.payments.reversal')}`
                      : ''}
                    {row.is_reversed
                      ? ` · ${t('page.orders.payments.reversed')}`
                      : ''}
                  </span>
                  <span className="ge-quiet">
                    {row.by ?? '—'}
                    {row.note ? ` · ${row.note}` : ''}
                  </span>
                </span>

                <span
                  className={
                    Number(row.amount) < 0
                      ? 'ge-pay__amount ge-pay__amount--off'
                      : 'ge-pay__amount'
                  }
                >
                  {money(row.amount)} {row.currency}
                  {/* Kurs pokazujemy tylko tam, gdzie cos zmienil. */}
                  {row.currency === board.base_currency ? null : (
                    <span className="ge-quiet">
                      {money(row.amount_base)} {board.base_currency} ·{' '}
                      {row.exchange_rate}
                    </span>
                  )}
                </span>

                {row.is_reversal || row.is_reversed ? (
                  <span />
                ) : (
                  <button
                    type="button"
                    className="ge-draw__remove"
                    aria-label={t('page.orders.payments.reverse')}
                    onClick={() => {
                      setReverseId(row.id);
                      setReverseNote('');
                    }}
                  >
                    <PiArrowUUpLeft />
                  </button>
                )}

                {reverseId === row.id && (
                  <div className="ge-pay__reverse">
                    <div className="ge-quiet">
                      {t('page.orders.payments.reverse_note')}
                    </div>
                    <input
                      className="ge-uf__input"
                      value={reverseNote}
                      placeholder={t('page.orders.payments.reverse_reason')}
                      onChange={(event) => setReverseNote(event.target.value)}
                    />
                    <Button
                      variant="contained"
                      size="small"
                      disabled={busy}
                      onClick={() => void reverse(row)}
                    >
                      {t('page.orders.payments.reverse_confirm')}
                    </Button>
                    <Button
                      variant="outlined"
                      size="small"
                      onClick={() => setReverseId(null)}
                    >
                      {t('page.orders.payments.cancel')}
                    </Button>
                  </div>
                )}
              </div>
            ))}
          </section>
        </div>

        <aside className="ge-card__side ge-card__side--right">
          <section className="ge-section">
            <div className="ge-section__head">
              {t('page.orders.payments.summary')}
            </div>

            <div className="ge-section__body">
              {/* Bez typu faktury nie ma brutto, wiec nie ma salda.
                  Piszemy to wprost zamiast pokazywac netto jako kwote
                  do zaplaty. */}
              {summary.gross === null ? (
                <div className="ge-warn">
                  {t('page.orders.payments.gross_unknown', {
                    net: money(summary.net),
                  })}
                </div>
              ) : (
                <div className="ge-from__row">
                  <span>{t('page.orders.payments.gross')}</span>
                  <span>{money(summary.gross)}</span>
                </div>
              )}

              <div className="ge-from__row">
                <span>{t('page.orders.payments.sum_paid')}</span>
                <span>{money(summary.paid)}</span>
              </div>

              {summary.due !== null && (
                <div className="ge-from__row ge-from__row--total">
                  <span>
                    {over
                      ? t('page.orders.payments.overpaid')
                      : t('page.orders.payments.due')}
                  </span>
                  <span>
                    {money(over ? String(-Number(summary.due)) : summary.due)}
                  </span>
                </div>
              )}
            </div>
          </section>

          {board.credit && (
            <section className="ge-section">
              <div className="ge-section__head">
                {t('page.orders.payments.credit')}
              </div>
              <div className="ge-section__body">
                <div className="ge-quiet">
                  {t('page.orders.payments.credit_note', {
                    outstanding: money(board.credit.outstanding),
                    limit: money(board.credit.limit),
                  })}
                </div>
                <div className="ge-quiet">
                  {t('page.orders.payments.credit_days', {
                    days: board.credit.payment_days,
                  })}
                </div>
              </div>
            </section>
          )}
        </aside>
      </div>
    </>
  );
}
