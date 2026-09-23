import { useEffect, useState } from 'react';
import { Link } from 'react-router';

import { useTranslation } from '@salvon/hooks/useTranslation';

import type { OfferRow, OffersBoard } from '@app/api/OffersApi';
import { OffersApi } from '@app/api/OffersApi';
import { ListWait } from '@app/components/list';

const money = (value: string | null) =>
  value === null
    ? '—'
    : new Intl.NumberFormat('pl-PL', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      }).format(Number(value));

const FILTERS = ['', 'issued', 'sent', 'accepted', 'rejected'] as const;

/**
 * Wszystkie oferty — ekran „Oferty" w panelu modułu.
 *
 * Do tej pory pozycja w menu była martwym odnośnikiem. Lista odpowiada
 * na pytanie, którego karta zlecenia zadać nie może: co teraz czeka
 * u klientów i co przepadło.
 */
export default function Page() {
  const t = useTranslation();
  const [board, setBoard] = useState<OffersBoard | null>(null);
  const [status, setStatus] = useState<string>('');
  const [loading, setLoading] = useState(true);

  const load = async (next: string) => {
    setLoading(true);

    const { content } = await OffersApi.board(next);
    const data: OffersBoard | undefined = content?.data;

    setLoading(false);

    if (data) {
      setBoard(data);
    }
  };

  useEffect(() => {
    void load(status);
  }, [status]);

  if (!board) {
    return <div className="ge-empty">{t('page.orders.card.loading')}</div>;
  }

  const amount = (row: OfferRow) =>
    row.price_display === 'gross' && row.gross !== null ? row.gross : row.net;

  return (
    <>
      <header className="ge-head">
        <div>
          <div className="ge-head__kicker">{t('page.menu.offers')}</div>
          <h1 className="ge-head__title">
            {t('page.orders.offers.count', { count: board.offers.length })}
          </h1>
        </div>
      </header>

      <div className="ge-segbar">
        <nav className="ge-seg ge-seg--filter" aria-label={t('page.menu.offers')}>
          {FILTERS.map((key) => {
            const count = board.counts[key === '' ? 'all' : key] ?? 0;

            return (
              <button
                type="button"
                key={key || 'all'}
                className={[
                  'ge-seg__item',
                  key === status ? 'is-active' : '',
                  count === 0 && key !== status ? 'ge-seg__item--empty' : '',
                ]
                  .filter(Boolean)
                  .join(' ')}
                onClick={() => setStatus(key)}
              >
                <span>
                  {t(`page.orders.offers.filter_${key === '' ? 'all' : key}`)}
                </span>
                {count > 0 && <span className="ge-seg__count">{count}</span>}
              </button>
            );
          })}
        </nav>
      </div>

      {/* Przelaczenie zakladki pobiera dane od nowa, a stare wiersze
          stoja na ekranie do konca — bez tego paska nie widac, ze
          cokolwiek sie dzieje. */}
      <ListWait on={loading} />

      {board.offers.length === 0 && !loading ? (
        <div className="ge-empty">{t('page.orders.offers.empty')}</div>
      ) : (
        <section className="ge-section">
          <div className="ge-offers__head ge-offers__head--all">
            <span>{t('page.orders.offers.number')}</span>
            <span>{t('page.orders.offers.contractor')}</span>
            <span>{t('page.orders.offers.form')}</span>
            <span className="r">{t('page.orders.offers.amount')}</span>
            <span>{t('page.orders.offers.state')}</span>
          </div>

          {board.offers.map((row) => (
            <div className="ge-offers__row ge-offers__row--all" key={row.id}>
              <span>
                <Link to={`/orders/${row.order_id}/oferty`} className="ge-link">
                  <strong>{row.number}</strong>
                </Link>
                <span className="ge-quiet ge-offers__by">
                  {row.issued_at.slice(0, 10)}
                  {row.issued_by ? ` · ${row.issued_by}` : ''}
                </span>
              </span>
              <span>{row.contractor ?? '—'}</span>
              <span className="ge-offers__form">
                <span className="ge-tag">
                  {t(`page.orders.offers.display_${row.price_display}`)}
                </span>
                {row.is_variant && (
                  <span className="ge-tag ge-tag--mod">
                    {t('page.orders.offers.variant')}
                  </span>
                )}
              </span>
              <span className="r">{money(amount(row))}</span>
              <span>
                <span className={`ge-offers__state is-${row.status}`}>
                  {row.status_label}
                </span>
                {row.is_expired && (
                  <span className="ge-note ge-note--warn">
                    {t('page.orders.offers.expired', {
                      date: row.valid_until ?? '',
                    })}
                  </span>
                )}
              </span>
            </div>
          ))}
        </section>
      )}
    </>
  );
}
