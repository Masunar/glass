import { Link } from 'react-router';

import { useTranslation } from '@salvon/hooks/useTranslation';

import type { OrderTabCounts } from '@app/api/OrdersApi';

type Tab = 'card' | 'panes' | 'drawings' | 'payments' | 'log';

/**
 * Zakładki zlecenia — ten sam pasek na każdym z czterech ekranów.
 *
 * Każda zakładka to osobny adres, nie stan wewnątrz ekranu: do formatek
 * konkretnego zlecenia wysyła się link i ten link ma otworzyć formatki.
 * Dopóki dziennik był przełącznikiem wewnątrz karty, z formatek nie dało
 * się do niego dojść ani go komuś podać.
 */
export default function OrderTabs({
  orderId,
  active,
  counts,
}: {
  orderId: number;
  active: Tab;
  counts: OrderTabCounts;
}) {
  const t = useTranslation();

  const tabs: { key: Tab; to: string; label: string; count?: number }[] = [
    {
      key: 'card',
      to: `/orders/${orderId}`,
      label: t('page.orders.card.tab_card'),
    },
    {
      key: 'panes',
      to: `/orders/${orderId}/formatki`,
      label: t('page.orders.card.tab_panes'),
      count: counts.panes,
    },
    {
      key: 'drawings',
      to: `/orders/${orderId}/rysunki`,
      label: t('page.orders.drawings.title'),
      count: counts.drawings,
    },
    {
      key: 'payments',
      to: `/orders/${orderId}/platnosci`,
      label: t('page.orders.payments.title'),
      count: counts.payments,
    },
    {
      key: 'log',
      to: `/orders/${orderId}/dziennik`,
      label: t('page.orders.card.tab_history'),
      count: counts.log,
    },
  ];

  /**
   * Licznik pokazuje się tylko wtedy, gdy jest co liczyć. Zakładka pusta
   * zostaje samą etykietą, przygaszoną — brak licznika jest informacją
   * i nie trzeba czytać liczby, żeby wiedzieć, że nie ma tam nic.
   */
  const label = (tab: { label: string; count?: number }) => {
    const empty = tab.count !== undefined && tab.count === 0;

    return (
      <>
        <span>{tab.label}</span>
        {empty || tab.count === undefined ? null : (
          <span className="ge-seg__count">{tab.count}</span>
        )}
      </>
    );
  };

  const className = (tab: { key: Tab; count?: number }) =>
    [
      'ge-seg__item',
      tab.key === active ? 'is-active' : '',
      tab.count === 0 && tab.key !== active ? 'ge-seg__item--empty' : '',
    ]
      .filter(Boolean)
      .join(' ');

  return (
    <div className="ge-segbar">
      <nav
        className="ge-seg ge-seg--nav"
        aria-label={t('page.orders.card.sections')}
      >
        {tabs.map((tab) =>
          tab.key === active ? (
            <span className={className(tab)} key={tab.key} aria-current="page">
              {label(tab)}
            </span>
          ) : (
            <Link className={className(tab)} to={tab.to} key={tab.key}>
              {label(tab)}
            </Link>
          ),
        )}
      </nav>
    </div>
  );
}
