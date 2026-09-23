import OrderDrawer from './_components/OrderDrawer';
import { useEffect, useMemo, useState } from 'react';
import { PiPlus, PiWarningCircle } from 'react-icons/pi';
import { useNavigate } from 'react-router';

import { Button } from '@salvon/components/button';
import { Flex } from '@salvon/components/div';
import { useTranslation } from '@salvon/hooks/useTranslation';

import {
  type OrderBandKey,
  type OrderBoard,
  type OrderRow,
  OrdersApi,
} from '@app/api/OrdersApi';
import HasPermission from '@app/components/HasPermission';
import AlertChips from '@app/components/alerts/AlertChips';
import {
  Band,
  type Column,
  DataList,
  ListHead,
  Row,
  Stage,
  Strip,
  Strips,
} from '@app/components/list';
import { Permission, SubPermission } from '@app/config/permission';

const columns: Column[] = [
  { labelKey: 'page.orders.column.number', width: '104px' },
  { labelKey: 'page.orders.column.contractor', width: 'minmax(220px, 1fr)' },
  { labelKey: 'page.orders.column.stage', width: '132px' },
  { labelKey: 'page.orders.column.deadline', width: '124px' },
  { labelKey: 'page.orders.column.handover', width: '140px' },
  { labelKey: 'page.orders.column.amount', width: '132px', align: 'right' },
  { labelKey: 'page.orders.column.next', width: '230px' },
];

/** Kropka etapu bierze kolor z tego, gdzie zlecenie stoi w procesie. */
const STAGE_TONE: Record<string, 'new' | 'prod' | 'done' | 'claim' | 'idle'> = {
  DO_WYCENY: 'idle',
  ZLECENIE: 'new',
  PRODUKCJA: 'prod',
  GOTOWE: 'done',
  DOSTAWA: 'done',
  ODBIOR: 'done',
  MONTAZ: 'done',
  NIEROZLICZONE: 'claim',
  ROZLICZONE: 'done',
};

export default function Page() {
  const t = useTranslation();
  const [board, setBoard] = useState<OrderBoard | null>(null);
  const [query, setQuery] = useState('');
  const [status, setStatus] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState<number | null>(null);
  const [loading, setLoading] = useState(true);
  const [formOpen, setFormOpen] = useState(false);
  const navigate = useNavigate();

  const load = async (
    nextQuery: string = query,
    nextStatus: string | null = status,
  ) => {
    setLoading(true);

    const { content } = await OrdersApi.board(nextQuery, nextStatus);
    const data: OrderBoard | undefined = content?.data;

    setLoading(false);

    if (!data) {
      // Bez tego nieudane pobranie zostawialo na ekranie poprzednia
      // liste — czyli odpowiedz na pytanie, ktorego juz nie zadano.
      setError(t('page.orders.load_failed'));

      return;
    }

    setError(null);
    setBoard(data);
  };

  useEffect(() => {
    void load('', null);
  }, []);

  /**
   * Przejście wykonane z listy, bez wchodzenia w zlecenie — po nim
   * wiersz zmienia pasmo, więc lista wczytuje się od nowa.
   */
  const run = async (row: OrderRow, transitionId: number) => {
    setBusy(row.id);
    setError(null);

    const { content, response } = await OrdersApi.transition(
      row.id,
      transitionId,
    );

    setBusy(null);

    if (!response.success) {
      setError(
        content?.errors?.transition?.[0] ??
          t('page.orders.transition_failed', { number: row.number }),
      );

      return;
    }

    await load();
  };

  const summary = board?.summary;
  const bands = useMemo(
    () => (board?.bands ?? []).filter((band) => band.rows.length > 0),
    [board],
  );

  const money = (value: string) =>
    new Intl.NumberFormat('pl-PL', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(Number(value));

  /** Termin słowem: „dziś", „2 dni po terminie" — nie surowa data. */
  const deadlineLabel = (row: OrderRow) => {
    if (row.days_left === null) {
      return { label: t('page.orders.no_deadline'), tone: 'plain' as const };
    }

    if (row.days_left === 0) {
      return { label: t('page.orders.today'), tone: 'today' as const };
    }

    if (row.days_left < 0) {
      return {
        label: t('page.orders.overdue_by', { count: -row.days_left }),
        tone: 'late' as const,
      };
    }

    return {
      label: t('page.orders.in_days', { count: row.days_left }),
      tone: 'plain' as const,
    };
  };

  return (
    <>
      <header className="ge-head">
        <div>
          <div className="ge-head__kicker">{t('page.module.zlec')}</div>
          <h1 className="ge-head__title">{t('page.orders.title')}</h1>
        </div>

        <div className="ge-head__actions">
          <input
            className="ge-search"
            type="search"
            value={query}
            placeholder={t('page.orders.search')}
            aria-label={t('page.orders.search')}
            onChange={(event) => {
              setQuery(event.target.value);
              void load(event.target.value);
            }}
          />
          <HasPermission
            permission={Permission.ORDERS}
            sub={SubPermission.CREATE}
          >
            <Button
              variant="contained"
              icon={<PiPlus />}
              onClick={() => setFormOpen(true)}
            >
              {t('page.orders.add')}
            </Button>
          </HasPermission>
        </div>
      </header>

      {error && <div className="ge-alert">{error}</div>}

      {summary && (
        <Strips>
          <Strip
            variant="module"
            label={t('page.orders.band.today')}
            value={summary.today}
            note={t('page.orders.strip.today_note')}
          />
          <Strip
            variant={summary.overdue > 0 ? 'alert' : 'plain'}
            label={t('page.orders.band.overdue')}
            value={summary.overdue}
            noteWarn={summary.overdue > 0}
            note={t('page.orders.strip.overdue_note')}
          />
          <Strip
            variant="plain"
            label={t('page.orders.strip.shown')}
            value={summary.shown}
            note={t('page.orders.strip.shown_note')}
          />
        </Strips>
      )}

      <div className="ge-segbar">
        <nav
          className="ge-seg ge-seg--filter"
          aria-label={t('page.orders.filters')}
        >
          {(board?.filters ?? []).map((filter) => {
            const here = filter.code === status;

            return (
              <button
                key={filter.code ?? 'all'}
                type="button"
                className={[
                  'ge-seg__item',
                  here ? 'is-active' : '',
                  // Filtr bez zlecen zostaje sama etykieta: pusty licznik
                  // i tak nie niesie nic poza zerem.
                  filter.count === 0 && !here ? 'ge-seg__item--empty' : '',
                ]
                  .filter(Boolean)
                  .join(' ')}
                aria-pressed={here}
                onClick={() => {
                  setStatus(filter.code);
                  void load(query, filter.code);
                }}
              >
                <span>{filter.name}</span>
                {filter.count === 0 ? null : (
                  <span className="ge-seg__count">{filter.count}</span>
                )}
                {filter.alerts > 0 && (
                  <span
                    className="ge-seg__alerts"
                    title={t('page.orders.alerts_title')}
                  >
                    {filter.alerts}
                  </span>
                )}
              </button>
            );
          })}
        </nav>
        <span className="ge-segbar__end">{t('page.orders.sorted_by')}</span>
      </div>

      <OrderDrawer
        open={formOpen}
        onClose={() => setFormOpen(false)}
        onCreated={(id) => {
          setFormOpen(false);
          // Zalozone zlecenie jest puste — czlowiek zaklada je po to,
          // zeby od razu dopisac pozycje, wiec ladujemy na karcie.
          void navigate(`/orders/${id}`);
        }}
      />

      <DataList
        columns={columns}
        loading={loading}
        empty={bands.length === 0 ? t('page.orders.empty') : undefined}
      >
        <ListHead columns={columns} translate={t} />

        {bands.map((band) => (
          <div key={band.key} style={{ display: 'contents' }}>
            <Band
              variant={bandVariant(band.key)}
              title={`${t(`page.orders.band.${band.key}`)} — ${band.count}`}
              meta={`${money(band.total)} zł`}
              end={
                band.key === 'overdue'
                  ? t('page.orders.band.overdue_meta')
                  : undefined
              }
            />

            {band.rows.map((row) => {
              const due = deadlineLabel(row);

              return (
                <Row
                  key={row.id}
                  to={`/orders/${row.id}`}
                  // Krawedz wiersza schodzi z regul, nie z warunku
                  // wpisanego tutaj. Dwa zrodla sygnalu rozjechalyby sie
                  // przy pierwszej zmianie progu w panelu.
                  alert={row.alerts.length > 0}
                >
                  <div>
                    <div className="ge-num">#{row.number}</div>
                    <div className="ge-note">{row.created_at ?? ''}</div>
                  </div>

                  <div style={{ minWidth: 0 }}>
                    <div className="ge-name">{row.contractor ?? '—'}</div>
                    <div className="ge-note">
                      {row.note ?? row.contractor_phone ?? ''}
                    </div>
                    {/* Powod wstrzymania i otwarta reklamacja jada teraz
                        znacznikiem z reguly, razem z reszta alertow —
                        wczesniej byly recznie wyliczonym wyjatkiem obok
                        silnika, ktory liczy to samo. */}
                    <AlertChips
                      marks={row.alerts}
                      // Odhaczenie zmienia liczniki przy zakladkach,
                      // wiec lista wczytuje sie od nowa — inaczej
                      // czerwona liczba klamie az do odswiezenia.
                      onChanged={() => void load()}
                    />
                  </div>

                  <Stage
                    label={row.status ?? '—'}
                    tone={STAGE_TONE[row.status_code ?? ''] ?? 'idle'}
                  />

                  <div>
                    <div
                      className={
                        due.tone === 'plain'
                          ? 'ge-due'
                          : `ge-due ge-due--${due.tone}`
                      }
                    >
                      {due.label}
                    </div>
                    {row.is_shifted && (
                      <div className="ge-note">{t('page.orders.shifted')}</div>
                    )}
                  </div>

                  <div>
                    <div>
                      {t(`page.orders.handover.${row.delivery_method}`)}
                    </div>
                    <div className="ge-note">{row.delivery_place ?? ''}</div>
                  </div>

                  <div
                    className="ge-money__value"
                    style={{ textAlign: 'right' }}
                  >
                    {money(row.amount)}
                  </div>

                  <NextCell
                    row={row}
                    t={t}
                    busy={busy === row.id}
                    onRun={(transitionId) => void run(row, transitionId)}
                  />
                </Row>
              );
            })}
          </div>
        ))}

        {bands.length === 0 && (
          <div className="ge-empty">{t('page.orders.empty')}</div>
        )}
      </DataList>
    </>
  );
}

function bandVariant(key: OrderBandKey): 'plain' | 'module' | 'alert' {
  if (key === 'today') {
    return 'module';
  }

  return key === 'overdue' ? 'alert' : 'plain';
}

/**
 * „Co dalej" — przycisk, gdy przejście jest dostępne; powód blokady,
 * gdy nie jest. Wiersz bez żadnej informacji o dalszym kroku jest
 * gorszy niż wiersz mówiący, czego brakuje.
 */
function NextCell({
  row,
  t,
  busy,
  onRun,
}: {
  row: OrderRow;
  t: (key: string, options?: Record<string, unknown>) => string;
  busy: boolean;
  onRun: (transitionId: number) => void;
}) {
  if (row.next_step) {
    const step = row.next_step;

    return (
      <Flex gap={1} align="center">
        {row.owner_initials && (
          <span className="ge-avatar">{row.owner_initials}</span>
        )}
        <HasPermission
          permission={Permission.ORDERS}
          sub={SubPermission.UPDATE}
        >
          <Button
            variant="contained"
            size="small"
            disabled={busy}
            onClick={(event) => {
              // Przycisk siedzi w klikalnym wierszu — bez tego klik
              // otwiera zlecenie zamiast wykonac przejscie.
              event.stopPropagation();
              onRun(step.transition_id);
            }}
          >
            {step.label}
          </Button>
        </HasPermission>
      </Flex>
    );
  }

  if (row.blocked_step) {
    return (
      <Flex gap={1} align="center">
        {row.owner_initials && (
          <span className="ge-avatar">{row.owner_initials}</span>
        )}
        <span
          className="ge-next__text"
          title={row.blocked_step.blocked_by ?? ''}
        >
          <PiWarningCircle style={{ verticalAlign: '-2px', marginRight: 4 }} />
          {row.blocked_step.blocked_by}
        </span>
      </Flex>
    );
  }

  return (
    <span className="ge-next__text">{t('page.orders.nothing_to_do')}</span>
  );
}
