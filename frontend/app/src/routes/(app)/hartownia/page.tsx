import { useCallback, useEffect, useState } from 'react';

import type { ResponseContent, ResponseProps } from '@salvon/request';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { notifyError, notifySuccess, notifyWarning } from '@salvon/utils/notify';

import type {
  TemperingBatchBoard,
  TemperingBatchCard,
  TemperingQueue as QueueBoard,
} from '@app/api/TemperingApi';
import { TemperingApi } from '@app/api/TemperingApi';

import { Batches } from './_components/Batches';
import { Queue } from './_components/Queue';

type Tab = 'queue' | 'batches';

/**
 * Hartownia — podzlecenie, nie stanowisko.
 *
 * Zakład nie ma własnego pieca, więc ten ekran jest buforem między
 * produkcją a podwykonawcą: zbiera szyby ze **wszystkich** zleceń,
 * pozwala skompletować wsad i śledzi, co wyjechało, a co wróciło.
 *
 * Jedyne miejsce w aplikacji zorganizowane w poprzek zleceń.
 */
export default function Page() {
  const t = useTranslation();

  const [tab, setTab] = useState<Tab>('queue');
  const [queue, setQueue] = useState<QueueBoard | null>(null);
  const [batches, setBatches] = useState<TemperingBatchBoard | null>(null);
  const [card, setCard] = useState<TemperingBatchCard | null>(null);

  const [thickness, setThickness] = useState('');
  const [status, setStatus] = useState('open');
  const [selected, setSelected] = useState<number[]>([]);
  const [creating, setCreating] = useState(false);

  const loadQueue = useCallback(async () => {
    const { content } = await TemperingApi.queue(thickness);
    const data: QueueBoard | undefined = content?.data;

    if (data) {
      setQueue(data);
    }
  }, [thickness]);

  const loadBatches = useCallback(async () => {
    const { content } = await TemperingApi.batches(status);
    const data: TemperingBatchBoard | undefined = content?.data;

    if (data) {
      setBatches(data);
    }
  }, [status]);

  const load = useCallback(async () => {
    await (tab === 'queue' ? loadQueue() : loadBatches());
  }, [tab, loadQueue, loadBatches]);

  useEffect(() => {
    void load();
  }, [load]);

  const openCard = async (id: number | null) => {
    if (id === null) {
      setCard(null);

      return;
    }

    const { content } = await TemperingApi.batch(id);
    const data: TemperingBatchCard | undefined = content?.data;

    if (data) {
      setCard(data);
    }
  };

  const act = async (call: () => Promise<ResponseProps<ResponseContent>>) => {
    const { response, content } = await call();

    if (!response.success) {
      notifyError(t('api.ise'));

      return;
    }

    const data = content?.data as TemperingBatchCard | undefined;

    if (data) {
      setCard(data);

      // Stluczka zaklada pozycje zastepcza w kolejce. Czlowiek ma to
      // zobaczyc teraz, a nie odkryc jutro, ze cos znow czeka na wsad.
      if (data.replaced !== undefined && data.replaced > 0) {
        notifyWarning(t('page.tempering.replaced', { count: data.replaced }));
      }
    }

    notifySuccess(t('api.save_success'));
    void loadBatches();
  };

  const createBatch = async (
    supplierId: number,
    expectedAt: string,
    note: string,
    vehicleId: string,
    departureAt: string,
  ) => {
    const { response, content } = await TemperingApi.createBatch(
      supplierId,
      selected,
      expectedAt,
      note,
      vehicleId,
      departureAt,
    );

    if (!response.success) {
      notifyError(t('api.ise'));

      return;
    }

    const data = content?.data as TemperingBatchCard | undefined;
    const skipped = data?.skipped ?? [];

    if (skipped.length > 0) {
      notifyWarning(t('page.tempering.skipped', { count: skipped.length }));
    }

    setSelected([]);
    setCreating(false);
    setCard(data ?? null);
    setTab('batches');
    setStatus('draft');
  };

  // Masa zaznaczenia jest potrzebna przy wyborze auta, a zaznaczenie
  // zyje tutaj — wiec liczymy ja tu, a nie w zakladce kolejki.
  const pickedKg = (queue?.rows ?? [])
    .filter((row) => selected.includes(row.id))
    .reduce((total, row) => total + row.kg, 0);

  const tabs: { key: Tab; label: string; count: number | null }[] = [
    {
      key: 'queue',
      label: t('page.tempering.tab_queue'),
      count: queue?.summary.shown ?? null,
    },
    {
      key: 'batches',
      label: t('page.tempering.tab_batches'),
      count: null,
    },
  ];

  return (
    <>
      <header className="ge-head">
        <div>
          <div className="ge-head__kicker">{t('page.module.prod')}</div>
          <h1 className="ge-head__title">{t('page.tempering.title')}</h1>
        </div>
      </header>

      <div className="ge-segbar">
        <nav className="ge-seg ge-seg--nav" aria-label={t('page.tempering.title')}>
          {tabs.map((item) => (
            <button
              key={item.key}
              type="button"
              className={tab === item.key ? 'ge-seg__item is-active' : 'ge-seg__item'}
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

      {creating && (
        <NewBatch
          t={t}
          suppliers={batches?.suppliers ?? []}
          vehicles={batches?.vehicles ?? []}
          count={selected.length}
          kg={pickedKg}
          onCancel={() => setCreating(false)}
          onCreate={(supplierId, expectedAt, note, vehicleId, departureAt) =>
            void createBatch(supplierId, expectedAt, note, vehicleId, departureAt)
          }
        />
      )}

      {tab === 'queue' && (
        <Queue
          board={queue}
          t={t}
          thickness={thickness}
          onThickness={setThickness}
          selected={selected}
          onToggle={(id) =>
            setSelected((current) =>
              current.includes(id)
                ? current.filter((value) => value !== id)
                : [...current, id],
            )
          }
          onClear={() => setSelected([])}
          onCreateBatch={() => {
            // Lista dostawcow przychodzi z zakladki partii, wiec przy
            // pierwszym uzyciu trzeba ja najpierw pobrac.
            void loadBatches();
            setCreating(true);
          }}
        />
      )}

      {tab === 'batches' && (
        <Batches
          board={batches}
          card={card}
          t={t}
          status={status}
          onStatus={(value) => {
            setStatus(value);
            setCard(null);
          }}
          onOpen={(id) => void openCard(id)}
          onPlan={(id, vehicleId, departureAt, expectedAt) =>
            void act(() =>
              TemperingApi.planBatch(id, vehicleId, departureAt, expectedAt),
            )
          }
          onSend={(id, sentAt) => void act(() => TemperingApi.sendBatch(id, sentAt))}
          onReceive={(id, outcomes, returnedAt) =>
            void act(() => TemperingApi.receiveBatch(id, outcomes, returnedAt))
          }
          onSettle={(id, cost, documentNo) =>
            void act(() => TemperingApi.settleBatch(id, cost, documentNo))
          }
          onCancel={(id) => void act(() => TemperingApi.cancelBatch(id))}
        />
      )}
    </>
  );
}

function NewBatch({
  t,
  suppliers,
  vehicles,
  count,
  kg,
  onCancel,
  onCreate,
}: {
  t: (key: string, options?: Record<string, unknown>) => string;
  suppliers: { id: number; name: string }[];
  vehicles: { id: number; name: string; payload_kg: number }[];
  count: number;
  kg: number;
  onCancel: () => void;
  onCreate: (
    supplierId: number,
    expectedAt: string,
    note: string,
    vehicleId: string,
    departureAt: string,
  ) => void;
}) {
  const [supplier, setSupplier] = useState<string>(
    suppliers.length > 0 ? String(suppliers[0].id) : '',
  );
  const [vehicle, setVehicle] = useState('');
  const [departureAt, setDepartureAt] = useState('');
  const [expectedAt, setExpectedAt] = useState('');
  const [note, setNote] = useState('');

  const picked = vehicles.find((row) => String(row.id) === vehicle);
  const over = picked !== undefined && kg > picked.payload_kg;

  if (suppliers.length === 0) {
    return <p className="ge-lead ge-lead--warn">{t('page.tempering.no_suppliers')}</p>;
  }

  return (
    <div className="ge-po ge-po--new">
      <label className="ge-po__field">
        {t('page.tempering.supplier')}
        <select
          className="ge-uf__select"
          value={supplier}
          onChange={(event) => setSupplier(event.target.value)}
        >
          {suppliers.map((row) => (
            <option key={row.id} value={row.id}>
              {row.name}
            </option>
          ))}
        </select>
      </label>
      <label className="ge-po__field">
        {t('page.tempering.vehicle')}
        <select
          className="ge-uf__select"
          value={vehicle}
          onChange={(event) => setVehicle(event.target.value)}
        >
          <option value="">{t('page.tempering.no_vehicle')}</option>
          {vehicles.map((row) => (
            <option key={row.id} value={row.id}>
              {row.name} · {row.payload_kg} kg
            </option>
          ))}
        </select>
      </label>
      <label className="ge-po__field">
        {t('page.tempering.departure_at')}
        <input
          type="date"
          className="ge-uf__input"
          value={departureAt}
          onChange={(event) => setDepartureAt(event.target.value)}
        />
      </label>
      <label className="ge-po__field">
        {t('page.tempering.expected_at')}
        <input
          type="date"
          className="ge-uf__input"
          value={expectedAt}
          onChange={(event) => setExpectedAt(event.target.value)}
        />
      </label>

      {/* Ostrzezenie przy wyborze auta, nie dopiero na karcie partii —
          zeby dalo sie zmienic decyzje, zanim kurs powstanie. */}
      {over && picked !== undefined && (
        <p className="ge-lead ge-lead--warn">
          {t('page.tempering.over_payload', {
            over: Math.round((kg - picked.payload_kg) * 100) / 100,
            vehicle: picked.name,
          })}
        </p>
      )}
      <label className="ge-po__field ge-po__field--wide">
        {t('page.warehouse.note')}
        <input
          className="ge-uf__input"
          value={note}
          onChange={(event) => setNote(event.target.value)}
        />
      </label>
      <div className="ge-po__foot">
        <button
          type="button"
          className="ge-act--go"
          onClick={() =>
            onCreate(Number(supplier), expectedAt, note, vehicle, departureAt)
          }
        >
          {t('page.tempering.create_batch_with', { count })}
        </button>
        <button type="button" onClick={onCancel}>
          {t('cancel')}
        </button>
      </div>
    </div>
  );
}
