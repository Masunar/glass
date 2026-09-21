import { useCallback, useEffect, useState } from 'react';

import type { ResponseContent, ResponseProps } from '@salvon/request';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { notifyError, notifySuccess, notifyWarning } from '@salvon/utils/notify';

import type {
  DemandBoard,
  DriftBoard,
  PurchaseOrderBoard,
  PurchaseOrderCard,
  SkippedSuggestion,
  StockBoard,
  StockRow,
} from '@app/api/WarehouseApi';
import { WarehouseApi } from '@app/api/WarehouseApi';

import { Demand } from './_components/Demand';
import { PriceDrift } from './_components/PriceDrift';
import { PurchaseOrders } from './_components/PurchaseOrders';
import { StockLevels } from './_components/StockLevels';

type Tab = 'levels' | 'demand' | 'orders' | 'drift';

/**
 * Magazyn: cztery pytania, cztery zakładki.
 *
 * „Stany" — co zamówić, żeby nie zabrakło. „Okucia w zamówieniach" —
 * czego brakuje pod to, co już sprzedaliśmy. „Zamówienia" — co jedzie
 * i co przyjąć. „Rozjazd cennika" — gdzie cena zakupu wyprzedziła cenę
 * sprzedaży, bo przyjęcie towaru jej nie przelicza.
 *
 * Na razie same okucia — to jedyna sekcja, która ma stany.
 */
export default function Page() {
  const t = useTranslation();

  const [tab, setTab] = useState<Tab>('levels');
  const [levels, setLevels] = useState<StockBoard | null>(null);
  const [demand, setDemand] = useState<DemandBoard | null>(null);
  const [orders, setOrders] = useState<PurchaseOrderBoard | null>(null);
  const [card, setCard] = useState<PurchaseOrderCard | null>(null);
  const [drift, setDrift] = useState<DriftBoard | null>(null);

  const [query, setQuery] = useState('');
  const [shortages, setShortages] = useState(false);
  const [status, setStatus] = useState('open');
  const [selected, setSelected] = useState<number[]>([]);

  const loadLevels = useCallback(async () => {
    const { content } = await WarehouseApi.levels(query, shortages);
    const data: StockBoard | undefined = content?.data;

    if (data) {
      setLevels(data);
    }
  }, [query, shortages]);

  const loadOrders = useCallback(async () => {
    const { content } = await WarehouseApi.orders(status);
    const data: PurchaseOrderBoard | undefined = content?.data;

    if (data) {
      setOrders(data);
    }
  }, [status]);

  const load = useCallback(async () => {
    if (tab === 'levels') {
      await loadLevels();

      return;
    }

    if (tab === 'orders') {
      await loadOrders();

      return;
    }

    if (tab === 'drift') {
      const { content } = await WarehouseApi.priceDrift();
      const data: DriftBoard | undefined = content?.data;

      if (data) {
        setDrift(data);
      }

      return;
    }

    const { content } = await WarehouseApi.demand();
    const data: DemandBoard | undefined = content?.data;

    if (data) {
      setDemand(data);
    }
  }, [tab, loadLevels, loadOrders]);

  useEffect(() => {
    void load();
  }, [load]);

  const openCard = async (id: number | null) => {
    if (id === null) {
      setCard(null);

      return;
    }

    const { content } = await WarehouseApi.order(id);
    const data: PurchaseOrderCard | undefined = content?.data;

    if (data) {
      setCard(data);
    }
  };

  /**
   * Każda akcja na zamówieniu wraca świeżą kartą, więc odświeżamy
   * kartę z odpowiedzi, a listę osobno. Ponowne pobranie karty po
   * zapisie pokazywałoby stan sprzed sekundy przy wolniejszej bazie.
   */
  const act = async (call: () => Promise<ResponseProps<ResponseContent>>) => {
    const { response, content } = await call();

    if (!response.success) {
      notifyError(t('api.ise'));

      return;
    }

    const data = content?.data as PurchaseOrderCard | undefined;

    if (data) {
      setCard(data);
    }

    notifySuccess(t('api.save_success'));
    void loadOrders();
  };

  const saveThresholds = async (row: StockRow, min: number, max: number) => {
    const { response } = await WarehouseApi.thresholds(row.product_id, min, max);

    if (!response.success) {
      notifyError(t('api.ise'));

      return;
    }

    notifySuccess(t('api.save_success'));
    void loadLevels();
  };

  const count = async (row: StockRow, counted: number) => {
    const { response } = await WarehouseApi.count(
      row.product_id,
      counted,
      t('page.warehouse.count_note'),
    );

    if (!response.success) {
      notifyError(t('api.ise'));

      return;
    }

    notifySuccess(t('api.save_success'));
    void loadLevels();
  };

  /**
   * Pominiętych trzeba pokazać, a nie przemilczeć: człowiek zaznaczył
   * dziesięć pozycji i ma prawo wiedzieć, że zamówienia objęły osiem.
   */
  const createOrders = async () => {
    const { response, content } = await WarehouseApi.ordersFromSuggestions(selected);

    if (!response.success) {
      notifyError(t('api.ise'));

      return;
    }

    const data = content?.data as
      | { orders: PurchaseOrderCard[]; skipped: SkippedSuggestion[] }
      | undefined;

    const skipped = data?.skipped ?? [];

    if (skipped.length > 0) {
      notifyWarning(
        t('page.warehouse.skipped', {
          count: skipped.length,
          names: skipped.map((row) => row.name).join(', '),
        }),
      );
    }

    const created = data?.orders.length ?? 0;

    if (created > 0) {
      notifySuccess(t('page.warehouse.orders_created', { count: created }));
    }

    setSelected([]);
    setTab('orders');
    setStatus('draft');
  };

  const tabs: { key: Tab; label: string; count: number | null }[] = [
    {
      key: 'levels',
      label: t('page.warehouse.tab_levels'),
      count: levels?.summary.to_order ?? null,
    },
    {
      key: 'demand',
      label: t('page.warehouse.tab_demand'),
      count: demand?.summary.missing ?? null,
    },
    {
      key: 'orders',
      label: t('page.warehouse.tab_orders'),
      count: null,
    },
    {
      key: 'drift',
      label: t('page.warehouse.tab_drift'),
      count: drift?.summary.drifted ?? null,
    },
  ];

  return (
    <>
      <header className="ge-head">
        <div>
          <div className="ge-head__kicker">{t('page.module.mag')}</div>
          <h1 className="ge-head__title">{t('page.warehouse.title')}</h1>
        </div>
      </header>

      <div className="ge-segbar">
        <nav className="ge-seg ge-seg--nav" aria-label={t('page.warehouse.title')}>
          {tabs.map((item) => (
            <button
              key={item.key}
              type="button"
              className={
                tab === item.key ? 'ge-seg__item is-active' : 'ge-seg__item'
              }
              aria-pressed={tab === item.key}
              onClick={() => setTab(item.key)}
            >
              <span>{item.label}</span>
              {item.count === null || item.count === 0 ? null : (
                <span className="ge-seg__count">{item.count}</span>
              )}
            </button>
          ))}
        </nav>
      </div>

      {tab === 'levels' && (
        <StockLevels
          board={levels}
          t={t}
          query={query}
          onQuery={setQuery}
          shortages={shortages}
          onShortages={setShortages}
          selected={selected}
          onToggle={(productId) =>
            setSelected((current) =>
              current.includes(productId)
                ? current.filter((id) => id !== productId)
                : [...current, productId],
            )
          }
          onClear={() => setSelected([])}
          onCreateOrders={() => void createOrders()}
          onSaveThresholds={(row, min, max) => void saveThresholds(row, min, max)}
          onCount={(row, counted) => void count(row, counted)}
        />
      )}

      {tab === 'demand' && <Demand board={demand} t={t} />}

      {tab === 'orders' && (
        <PurchaseOrders
          board={orders}
          card={card}
          t={t}
          status={status}
          onStatus={(value) => {
            setStatus(value);
            setCard(null);
          }}
          onOpen={(id) => void openCard(id)}
          onCreate={(supplierId, expectedAt, note) =>
            void act(() => WarehouseApi.createOrder(supplierId, expectedAt, note))
          }
          onSend={(id) => void act(() => WarehouseApi.sendOrder(id))}
          onCancel={(id) => void act(() => WarehouseApi.cancelOrder(id))}
          onReceive={(id, lines, receivedAt, documentNo) =>
            void act(() => WarehouseApi.receiveOrder(id, lines, receivedAt, documentNo))
          }
        />
      )}

      {tab === 'drift' && <PriceDrift board={drift} t={t} />}
    </>
  );
}
