import OrderTabs from '../_components/OrderTabs';
import { useEffect, useState } from 'react';
import { useParams } from 'react-router';

import { useTranslation } from '@salvon/hooks/useTranslation';

import type { OrderCard } from '@app/api/OrdersApi';
import { OrdersApi } from '@app/api/OrdersApi';

/**
 * Dziennik zmian zlecenia.
 *
 * Stary log zapisywał sam fakt zmiany („Zmienił rabaty") — dwadzieścia
 * jeden identycznych wpisów w ciągu pół godziny. Tutaj każdy wiersz
 * mówi, co się zmieniło i z czego na co.
 */
export default function Page() {
  const t = useTranslation();
  const params = useParams();
  const id = Number(params.id);

  const [card, setCard] = useState<OrderCard | null>(null);

  useEffect(() => {
    void (async () => {
      const { content } = await OrdersApi.card(id);
      const data: OrderCard | undefined = content?.data;

      if (data) {
        setCard(data);
      }
    })();
  }, [id]);

  if (!card) {
    return <div className="ge-empty">{t('page.orders.card.loading')}</div>;
  }

  return (
    <>
      <header className="ge-head">
        <div>
          <div className="ge-head__kicker">
            {t('page.orders.card.tab_history')} · #{card.order.number}
          </div>
          <h1 className="ge-head__title">
            {card.order.contractor?.display_name ?? '—'}
          </h1>
        </div>
      </header>

      <OrderTabs orderId={id} active="log" counts={card.tabs} />

      <div className="ge-card">
        <div className="ge-card__main">
          <section className="ge-section">
            {card.history.length === 0 ? (
              <div className="ge-quiet">{t('page.orders.card.no_history')}</div>
            ) : (
              <div className="ge-log">
                {card.history.map((entry, index) => (
                  <div className="ge-log__entry" key={index}>
                    <span className="ge-log__time">{entry.at}</span>
                    <span>
                      <strong className="ge-log__who">
                        {entry.user ?? t('page.orders.card.system')}
                      </strong>
                      {entry.changes.map((change, position) => (
                        <span className="ge-log__change" key={position}>
                          {change.field}:{' '}
                          <span className="ge-log__before">
                            {String(change.before ?? '—')}
                          </span>{' '}
                          → <strong>{String(change.after ?? '—')}</strong>
                        </span>
                      ))}
                    </span>
                  </div>
                ))}
              </div>
            )}
          </section>
        </div>
      </div>
    </>
  );
}
