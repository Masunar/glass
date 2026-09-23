import { useEffect, useState } from 'react';
import { PiWarningCircle } from 'react-icons/pi';
import { Link } from 'react-router';

import { useTranslation } from '@salvon/hooks/useTranslation';

import type {
  DashboardBoard,
  DashboardOfferRow,
  DashboardOrderRow,
} from '@app/api/DashboardApi';
import { DashboardApi } from '@app/api/DashboardApi';
import { ListWait } from '@app/components/list';
import { Strip, Strips } from '@app/components/list';

/**
 * Pulpit — najpierw to, co czeka na mnie, potem stan zakładu.
 *
 * Do #31 stało tu demo Salvona: pierwszy ekran po zalogowaniu był
 * jedynym miejscem w aplikacji, które do niej nie należało.
 *
 * **Pulpit nie liczy niczego sam** — każda liczba pochodzi z tej samej
 * usługi co ekran, do którego prowadzi. Dlatego każdy kafelek jest
 * odnośnikiem: liczba bez miejsca, w które można z nią pójść, każe
 * szukać jej ręcznie na liście.
 *
 * Sekcja, do której brakuje uprawnień, **nie przychodzi z serwera** —
 * nie jest tu ukrywana.
 */
export default function Page() {
  const t = useTranslation();
  const [board, setBoard] = useState<DashboardBoard | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    void (async () => {
      const { content } = await DashboardApi.board();
      const data: DashboardBoard | undefined = content?.data;

      setLoading(false);

      if (data) {
        setBoard(data);
      }
    })();
  }, []);

  if (!board) {
    return (
      <>
        <ListWait on={loading} />
        {!loading && <div className="ge-empty">{t('page.home.failed')}</div>}
      </>
    );
  }

  const { mine, orders, production, warehouse, offers } = board;
  const empty =
    mine === null &&
    orders === null &&
    production === null &&
    warehouse === null &&
    offers === null;

  return (
    <>
      <header className="ge-head">
        <div>
          <div className="ge-head__kicker">{t('page.home.kicker')}</div>
          <h1 className="ge-head__title">{t('page.home.title')}</h1>
          <div className="ge-quiet">{t('page.home.lead')}</div>
        </div>
      </header>

      {empty && (
        /* Konto bez zadnego modulu. Pusty ekran bez slowa wygladalby
           jak awaria, a to jest stan konfiguracji. */
        <div className="ge-note ge-note--warn ge-acc__banner">
          <PiWarningCircle /> {t('page.home.no_access')}
        </div>
      )}

      {mine !== null && (mine.orders_total > 0 || mine.offers_total > 0) && (
        <section className="ge-section">
          <div className="ge-section__head ge-section__head--strong">
            {t('page.home.mine')}
          </div>

          {mine.orders.map((row) => (
            <OrderLine key={row.id} row={row} t={t} />
          ))}

          {mine.offers.map((row) => (
            <OfferLine key={row.id} row={row} t={t} />
          ))}

          <More
            shown={mine.orders.length + mine.offers.length}
            total={mine.orders_total + mine.offers_total}
            to="/orders"
            t={t}
          />
        </section>
      )}

      <Strips>
        {orders !== null && (
          <Strip
            variant={orders.overdue > 0 ? 'alert' : 'plain'}
            label={t('page.home.strip.overdue')}
            value={orders.overdue}
            noteWarn={orders.overdue > 0}
            note={t('page.home.strip.today', { count: orders.today })}
          />
        )}
        {production?.queue != null && (
          <Strip
            variant="prod"
            label={t('page.home.strip.queue')}
            value={production.queue.waiting}
            noteWarn={production.queue.problems > 0}
            note={t('page.home.strip.problems', {
              count: production.queue.problems,
            })}
          />
        )}
        {production?.furnace != null && (
          <Strip
            variant="module"
            label={t('page.home.strip.furnace')}
            value={production.furnace.waiting}
            note={t('page.home.strip.kg', { value: production.furnace.kg })}
          />
        )}
        {warehouse !== null && (
          <Strip
            variant={warehouse.shortages > 0 ? 'alert' : 'plain'}
            label={t('page.home.strip.shortages')}
            value={warehouse.shortages}
            noteWarn={warehouse.shortages > 0}
            note={t('page.home.strip.shortages_note')}
          />
        )}
        {offers !== null && (
          <Strip
            variant="money"
            label={t('page.home.strip.offers')}
            value={offers.open}
            note={t('page.home.strip.offers_note')}
          />
        )}
      </Strips>

      {orders !== null && orders.ready_total > 0 && (
        <section className="ge-section">
          <div className="ge-section__head">
            {t('page.home.ready')}
            <span className="ge-section__end ge-quiet">
              {t('page.home.ready_note')}
            </span>
          </div>

          {orders.ready.map((row) => (
            <OrderLine key={row.id} row={row} t={t} />
          ))}

          <More
            shown={orders.ready.length}
            total={orders.ready_total}
            to="/orders"
            t={t}
          />
        </section>
      )}

      {orders !== null && orders.blocked.length > 0 && (
        <section className="ge-section">
          <div className="ge-section__head">{t('page.home.blocked')}</div>
          <div className="ge-quiet">{t('page.home.blocked_note')}</div>

          {/* Po powodzie, nie po zleceniu: „piec zlecen czeka na
              rysunki" mowi co zrobic, piec identycznych wierszy nie. */}
          {orders.blocked.map((item) => (
            <div className="ge-home__blocked" key={item.reason}>
              <span className="ge-home__count">{item.count}</span>
              <span>{item.reason}</span>
            </div>
          ))}
        </section>
      )}

      {warehouse !== null && warehouse.rows.length > 0 && (
        <section className="ge-section">
          <div className="ge-section__head">{t('page.home.shortages')}</div>

          {warehouse.rows.map((row) => (
            <div className="ge-home__line" key={row.product_id}>
              <Link to="/magazyn" className="ge-link">
                {row.name}
              </Link>
              <span className="ge-quiet">
                {t('page.home.shortage_line', {
                  available: row.available,
                  order: row.to_order,
                })}
              </span>
            </div>
          ))}

          <More
            shown={warehouse.rows.length}
            total={warehouse.shortages}
            to="/magazyn"
            t={t}
          />
        </section>
      )}

      {offers !== null && offers.rows.length > 0 && (
        <section className="ge-section">
          <div className="ge-section__head">{t('page.home.offers')}</div>

          {offers.rows.map((row) => (
            <OfferLine key={row.id} row={row} t={t} />
          ))}

          <More
            shown={offers.rows.length}
            total={offers.open}
            to="/offers"
            t={t}
          />
        </section>
      )}
    </>
  );
}

type Translate = (key: string, options?: Record<string, unknown>) => string;

/** Zlecenie w jednej linii: numer, kontrahent i ruch, który czeka. */
function OrderLine({ row, t }: { row: DashboardOrderRow; t: Translate }) {
  return (
    <div className="ge-home__line">
      <Link to={`/orders/${row.id}`} className="ge-link">
        #{row.number}
      </Link>
      <span>{row.contractor ?? '—'}</span>
      <span className="ge-quiet">
        {row.next_step?.label ?? row.status ?? ''}
      </span>
      {row.days_left !== null && row.days_left < 0 && (
        <span className="ge-note ge-note--warn">
          {t('page.home.days_over', { count: Math.abs(row.days_left) })}
        </span>
      )}
    </div>
  );
}

function OfferLine({ row, t }: { row: DashboardOfferRow; t: Translate }) {
  return (
    <div className="ge-home__line">
      <Link to={`/orders/${row.order_id}/oferty`} className="ge-link">
        {row.number}
      </Link>
      <span>{row.contractor ?? '—'}</span>
      <span className="ge-quiet">{row.status_label}</span>
      {row.is_expired && (
        /* Wygasla to nie to samo co odrzucona: klient nie odpowiedzial,
           a termin minal. */
        <span className="ge-note ge-note--warn">
          {t('page.home.offer_expired')}
        </span>
      )}
    </div>
  );
}

/** „Widać 6 z 23" plus wyjście na listę, gdy jest czego więcej. */
function More({
  shown,
  total,
  to,
  t,
}: {
  shown: number;
  total: number;
  to: string;
  t: Translate;
}) {
  if (total <= shown) {
    return null;
  }

  return (
    <div className="ge-home__more">
      <Link to={to} className="ge-link">
        {t('page.home.more', { count: total - shown })}
      </Link>
    </div>
  );
}
