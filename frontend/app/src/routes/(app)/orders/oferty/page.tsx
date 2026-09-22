import OrderTabs from '../_components/OrderTabs';
import { useEffect, useState } from 'react';
import { PiPlus } from 'react-icons/pi';
import { useParams } from 'react-router';

import { Button } from '@salvon/components/button';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { notifyError, notifySuccess } from '@salvon/utils/notify';

import type { OfferRow, OrderOffersBoard } from '@app/api/OffersApi';
import { OffersApi } from '@app/api/OffersApi';

import OfferDrawer from '../_components/OfferDrawer';

const money = (value: string | null) =>
  value === null
    ? '—'
    : new Intl.NumberFormat('pl-PL', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      }).format(Number(value));

/**
 * Historia ofert zlecenia.
 *
 * Wiersz odpowiada wprost na pytanie ze zgłoszenia — „czy ostatnia
 * oferta była netto/brutto/wariantowa" — więc te trzy rzeczy stoją
 * w wierszu, a nie w szczegółach.
 *
 * Oferty się nie edytuje. Poprawka to następne wystawienie, a stara
 * wersja zostaje: dokument, który da się jeszcze zmienić, nie jest
 * dowodem na to, co dostał klient.
 */
export default function Page() {
  const t = useTranslation();
  const params = useParams();
  const id = Number(params.id);

  const [board, setBoard] = useState<OrderOffersBoard | null>(null);
  const [issueOpen, setIssueOpen] = useState(false);
  const [busy, setBusy] = useState(false);
  const [rejectId, setRejectId] = useState<number | null>(null);
  const [reason, setReason] = useState('');
  const [acceptId, setAcceptId] = useState<number | null>(null);
  const [variantId, setVariantId] = useState<string>('');

  const load = async () => {
    const { content } = await OffersApi.forOrder(id);
    const data: OrderOffersBoard | undefined = content?.data;

    if (data) {
      setBoard(data);
    }
  };

  useEffect(() => {
    void load();
  }, [id]);

  if (!board) {
    return <div className="ge-empty">{t('page.orders.card.loading')}</div>;
  }

  const run = async (
    action: () => Promise<{ content: any; response: { success: boolean } }>,
    errorKey: string,
  ) => {
    setBusy(true);
    const { content, response } = await action();
    setBusy(false);

    if (!response.success) {
      notifyError(content?.errors?.[errorKey]?.[0] ?? t('api.ise'));

      return false;
    }

    notifySuccess(t('api.save_success'));
    await load();

    return true;
  };

  const markSent = (row: OfferRow) =>
    void run(() => OffersApi.markSent(id, row.id), 'offer');

  const accept = async (row: OfferRow) => {
    const ok = await run(
      () =>
        OffersApi.accept(id, row.id, variantId === '' ? null : Number(variantId)),
      'offer',
    );

    if (ok) {
      setAcceptId(null);
      setVariantId('');
    }
  };

  const reject = async (row: OfferRow) => {
    const ok = await run(
      () => OffersApi.reject(id, row.id, reason),
      'rejection_reason',
    );

    if (ok) {
      setRejectId(null);
      setReason('');
    }
  };

  const current = board.current;

  return (
    <>
      <header className="ge-head">
        <div>
          <div className="ge-head__kicker">
            {t('page.orders.offers.title')} · #{board.order.number}
          </div>
          <h1 className="ge-head__title">
            {board.offers.length === 0
              ? t('page.orders.offers.none')
              : t('page.orders.offers.count', { count: board.offers.length })}
          </h1>
          <div className="ge-quiet">{board.order.contractor ?? '—'}</div>
        </div>

        <div className="ge-head__actions">
          <Button
            variant="contained"
            icon={<PiPlus />}
            onClick={() => setIssueOpen(true)}
          >
            {t('page.orders.offers.issue')}
          </Button>
        </div>
      </header>

      <OrderTabs orderId={id} active="offers" counts={board.tabs} />

      <div className="ge-card">
        <div className="ge-card__main">
          {board.offers.length === 0 ? (
            <div className="ge-empty">{t('page.orders.offers.empty')}</div>
          ) : (
            <section className="ge-section">
              <div className="ge-offers__head">
                <span>{t('page.orders.offers.number')}</span>
                <span>{t('page.orders.offers.form')}</span>
                <span className="r">{t('page.orders.offers.amount')}</span>
                <span>{t('page.orders.offers.state')}</span>
                <span>{t('page.orders.offers.actions')}</span>
              </div>

              {board.offers.map((row) => (
                <div className="ge-offers__row" key={row.id}>
                  <span>
                    <strong>{row.number}</strong>
                    <span className="ge-quiet ge-offers__by">
                      {row.issued_at.slice(0, 10)}
                      {row.issued_by ? ` · ${row.issued_by}` : ''}
                    </span>
                  </span>

                  {/* Trzy rzeczy z pytania Z-Z-03 stoja w wierszu, bo to
                      one sa odpowiedzia — nie trzeba otwierac oferty. */}
                  <span className="ge-offers__form">
                    <span className="ge-tag">
                      {t(`page.orders.offers.display_${row.price_display}`)}
                    </span>
                    <span className="ge-tag">
                      {t(`page.orders.offers.detail_${row.detail_level}`)}
                    </span>
                    {row.is_variant && (
                      <span className="ge-tag ge-tag--mod">
                        {t('page.orders.offers.variant')}
                      </span>
                    )}
                  </span>

                  <span className="r">
                    {money(
                      row.price_display === 'gross' && row.gross !== null
                        ? row.gross
                        : row.net,
                    )}
                    <span className="ge-quiet ge-offers__by">
                      {row.price_display === 'gross' && row.gross !== null
                        ? t('page.orders.offers.gross_short')
                        : t('page.orders.offers.net_short')}
                    </span>
                  </span>

                  <span>
                    <span className={`ge-offers__state is-${row.status}`}>
                      {row.status_label}
                    </span>
                    {/* Wygasla to nie odrzucona: klient nie odpowiedzial,
                        a termin minal. Jedno mowi o kliencie, drugie o nas. */}
                    {row.is_expired && (
                      <span className="ge-note ge-note--warn">
                        {t('page.orders.offers.expired', {
                          date: row.valid_until ?? '',
                        })}
                      </span>
                    )}
                    {row.rejection_reason && (
                      <span className="ge-quiet ge-offers__by">
                        {row.rejection_reason}
                      </span>
                    )}
                    {row.accepted_list !== null && (
                      <span className="ge-quiet ge-offers__by">
                        {t('page.orders.offers.accepted_list', {
                          number: row.accepted_list,
                        })}
                      </span>
                    )}
                  </span>

                  <span className="ge-offers__actions">
                    {row.status === 'issued' && (
                      <button
                        type="button"
                        disabled={busy}
                        onClick={() => markSent(row)}
                      >
                        {t('page.orders.offers.mark_sent')}
                      </button>
                    )}
                    {(row.status === 'issued' || row.status === 'sent') && (
                      <>
                        <button
                          type="button"
                          disabled={busy}
                          onClick={() => {
                            setAcceptId(row.id);
                            setRejectId(null);
                          }}
                        >
                          {t('page.orders.offers.accept')}
                        </button>
                        <button
                          type="button"
                          disabled={busy}
                          onClick={() => {
                            setRejectId(row.id);
                            setAcceptId(null);
                          }}
                        >
                          {t('page.orders.offers.reject')}
                        </button>
                      </>
                    )}
                  </span>

                  {acceptId === row.id && (
                    <div className="ge-offers__ask">
                      {board.variants.length === 0 ? (
                        <span className="ge-quiet">
                          {t('page.orders.offers.accept_no_variants')}
                        </span>
                      ) : (
                        <label className="ge-uf">
                          <span className="ge-uf__label">
                            {t('page.orders.offers.accepted_variant')}
                          </span>
                          <select
                            className="ge-uf__input ge-uf__select"
                            value={variantId}
                            onChange={(event) =>
                              setVariantId(event.target.value)
                            }
                          >
                            <option value="">
                              {t('page.orders.offers.variant_none')}
                            </option>
                            {board.variants.map((variant) => (
                              <option key={variant.id} value={variant.id}>
                                {t('page.orders.offers.variant_option', {
                                  number: variant.number,
                                  name: variant.name ?? '—',
                                  amount: money(variant.net),
                                })}
                              </option>
                            ))}
                          </select>
                          <span className="ge-uf__hint">
                            {t('page.orders.offers.accepted_variant_hint')}
                          </span>
                        </label>
                      )}
                      <div className="ge-offers__ask-end">
                        <Button
                          variant="text"
                          onClick={() => setAcceptId(null)}
                        >
                          {t('cancel')}
                        </Button>
                        <Button
                          variant="contained"
                          loading={busy}
                          onClick={() => void accept(row)}
                        >
                          {t('page.orders.offers.accept')}
                        </Button>
                      </div>
                    </div>
                  )}

                  {rejectId === row.id && (
                    <div className="ge-offers__ask">
                      <label className="ge-uf">
                        <span className="ge-uf__label">
                          {t('page.orders.offers.reason')}
                        </span>
                        <input
                          className="ge-uf__input"
                          value={reason}
                          placeholder={t('page.orders.offers.reason_hint')}
                          onChange={(event) => setReason(event.target.value)}
                        />
                        <span className="ge-uf__hint">
                          {t('page.orders.offers.reason_note')}
                        </span>
                      </label>
                      <div className="ge-offers__ask-end">
                        <Button
                          variant="text"
                          onClick={() => setRejectId(null)}
                        >
                          {t('cancel')}
                        </Button>
                        <Button
                          variant="contained"
                          loading={busy}
                          onClick={() => void reject(row)}
                        >
                          {t('page.orders.offers.reject')}
                        </Button>
                      </div>
                    </div>
                  )}
                </div>
              ))}
            </section>
          )}
        </div>

        <aside className="ge-card__side ge-card__side--right">
          {/* Stan zlecenia dzis obok tego, co poszlo do klienta. Bez
              tego historia mowi tylko „bylo", a pytanie brzmi zwykle
              „czy cos sie od tego czasu zmienilo". */}
          <section className="ge-section">
            <div className="ge-section__head">
              {t('page.orders.offers.current')}
            </div>
            <div className="ge-kv">
              <span className="ge-kv__k">
                {t('page.orders.offers.net_short')}
              </span>
              <span>{money(current.net)}</span>
            </div>
            <div className="ge-kv">
              <span className="ge-kv__k">
                {t('page.orders.offers.gross_short')}
              </span>
              <span>{money(current.gross)}</span>
            </div>
            {current.unknown_reason && (
              <div className="ge-note ge-note--warn">
                {current.unknown_reason}
              </div>
            )}
            <div className="ge-quiet">{t('page.orders.offers.current_note')}</div>
          </section>
        </aside>
      </div>

      <OfferDrawer
        orderId={id}
        current={board.current}
        open={issueOpen}
        onClose={() => setIssueOpen(false)}
        onSaved={() => {
          setIssueOpen(false);
          void load();
        }}
      />
    </>
  );
}
