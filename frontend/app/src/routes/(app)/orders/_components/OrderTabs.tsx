import { Link } from 'react-router';

import { useTranslation } from '@salvon/hooks/useTranslation';

import type { OrderTabCounts } from '@app/api/OrdersApi';

type Tab = 'card' | 'panes' | 'drawings' | 'log';

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
      key: 'log',
      to: `/orders/${orderId}/dziennik`,
      label: t('page.orders.card.tab_history'),
      count: counts.log,
    },
  ];

  return (
    <nav className="ge-filters" aria-label={t('page.orders.card.sections')}>
      {tabs.map((tab) =>
        tab.key === active ? (
          <span className="ge-filters__here" key={tab.key}>
            {tab.label}
            {tab.count === undefined ? '' : ` ${tab.count}`}
          </span>
        ) : (
          <Link to={tab.to} key={tab.key}>
            {tab.label}
            {tab.count === undefined ? '' : ` ${tab.count}`}
          </Link>
        ),
      )}
    </nav>
  );
}
