import OrderDrawer from './_components/OrderDrawer';
import { useEffect, useMemo, useState } from 'react';
import { PiCaretDown, PiPlus, PiWarningCircle } from 'react-icons/pi';
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
import { PreferencesApi } from '@app/api/PreferencesApi';
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
  const [mine, setMine] = useState(false);
  const [page, setPage] = useState(1);
  const [phase, setPhase] = useState<string | null>(null);
  const [openMenu, setOpenMenu] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState<number | null>(null);
  const [loading, setLoading] = useState(true);
  const [formOpen, setFormOpen] = useState(false);
  const navigate = useNavigate();

  const load = async (
    nextQuery: string = query,
    nextStatus: string | null = status,
    nextMine: boolean = mine,
    // Po akcji w wierszu lista wczytuje sie od nowa na tej samej
    // stronie; zmiana filtra zawsze wraca na pierwsza.
    nextPage: number = page,
    nextPhase: string | null = phase,
  ) => {
    setLoading(true);
    setPage(nextPage);
    setPhase(nextPhase);

    const { content } = await OrdersApi.board(
      nextQuery,
      nextStatus,
      nextMine,
      nextPage,
      nextPhase,
    );
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
    void load('', null, false, 1, null);
  }, []);

  // Menu fazy zamyka sie kliknieciem obok — inaczej zostaje otwarte nad
  // lista, az ktos trafi drugi raz dokladnie w ten sam przycisk.
  useEffect(() => {
    if (openMenu === null) {
      return;
    }

    const close = (event: MouseEvent) => {
      if (!(event.target as Element | null)?.closest('.ge-seg__phase')) {
        setOpenMenu(null);
      }
    };

    document.addEventListener('mousedown', close);

    return () => document.removeEventListener('mousedown', close);
  }, [openMenu]);

  /** Wybór statusu albo całej fazy — zawsze od pierwszej strony. */
  const pick = (nextStatus: string | null, nextPhase: string | null) => {
    setOpenMenu(null);
    setStatus(nextStatus);
    void load(query, nextStatus, mine, 1, nextPhase);
  };

  /** Liczba wierszy zapamiętana przy koncie, nie w przeglądarce. */
  const changePerPage = async (perPage: number) => {
    const { response } = await PreferencesApi.save({
      'orders.per_page': perPage,
    });

    if (!response.success) {
      setError(t('api.ise'));

      return;
    }

    await load(query, status, mine, 1, phase);
  };

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
  const inProgress =
    (board?.filters ?? []).find((filter) => filter.phase === null) ?? null;
  // Fazy w kolejnosci procesu; faza bez ani jednego zlecenia nie
  // dostaje zakladki, tak samo jak pusty status wczesniej.
  const phaseGroups = (board?.phases ?? [])
    .map((group) => {
      const filters = (board?.filters ?? []).filter(
        (filter) => filter.phase === group.key,
      );

      return {
        ...group,
        filters,
        count: filters.reduce((sum, filter) => sum + filter.count, 0),
        alerts: filters.reduce((sum, filter) => sum + filter.alerts, 0),
      };
    })
    .filter((group) => group.filters.length > 0);
  const bands = useMemo(
    () => (board?.bands ?? []).filter((band) => band.rows.length > 0),
    [board],
  );

  // Pusta lista pod wlaczonym „moje" znaczy co innego niz pusta baza —
  // zdanie „nie ma zlecen" kazaloby szukac bledu tam, gdzie go nie ma.
  const emptyLabel = board?.summary.mine
    ? t('page.orders.empty_mine')
    : t('page.orders.empty');

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
              void load(event.target.value, status, mine, 1);
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
            // Przyciecie do strony musi byc widac — inaczej 200 wierszy
            // wyglada jak cala baza.
            note={
              summary.total > summary.shown
                ? t('page.orders.strip.shown_of', {
                    from: (summary.page - 1) * summary.per_page + 1,
                    to: (summary.page - 1) * summary.per_page + summary.shown,
                    total: summary.total,
                  })
                : t('page.orders.strip.shown_note')
            }
          />
        </Strips>
      )}

      <div className="ge-segbar">
        {/* Fazy procesu zamiast dwunastu statusow w rzedzie: przewijany
            pasek ucinal „W toku" w polowie slowa, a przelacznik obok
            lamal sie na dwie linie. Faza z jednym statusem jest zwykla
            zakladka; z kilkoma — otwiera menu z cala faza na gorze. */}
        <nav
          className="ge-seg ge-seg--filter"
          aria-label={t('page.orders.filters')}
        >
          {inProgress && (
            <SegTab
              name={inProgress.name}
              count={inProgress.count}
              alerts={inProgress.alerts}
              active={status === null && phase === null}
              alertsTitle={t('page.orders.alerts_title')}
              onClick={() => pick(null, null)}
            />
          )}

          {phaseGroups.map((group) => {
            if (group.filters.length === 1) {
              const only = group.filters[0];

              return (
                <SegTab
                  key={group.key}
                  name={only.name}
                  count={only.count}
                  alerts={only.alerts}
                  active={status === only.code}
                  alertsTitle={t('page.orders.alerts_title')}
                  onClick={() => pick(only.code, null)}
                />
              );
            }

            const chosen =
              group.filters.find((filter) => filter.code === status) ?? null;
            const active = chosen !== null || phase === group.key;

            return (
              <div className="ge-seg__phase" key={group.key}>
                <button
                  type="button"
                  className={active ? 'ge-seg__item is-active' : 'ge-seg__item'}
                  aria-expanded={openMenu === group.key}
                  onClick={() =>
                    setOpenMenu((value) =>
                      value === group.key ? null : group.key,
                    )
                  }
                >
                  {/* Wybrany status staje sie etykieta fazy, zeby bylo
                      widac, co jest na liscie. */}
                  <span>{chosen?.name ?? group.name}</span>
                  <span className="ge-seg__count">
                    {chosen?.count ?? group.count}
                  </span>
                  {(chosen?.alerts ?? group.alerts) > 0 && (
                    <span
                      className="ge-seg__alerts"
                      title={t('page.orders.alerts_title')}
                    >
                      {chosen?.alerts ?? group.alerts}
                    </span>
                  )}
                  <PiCaretDown />
                </button>

                {openMenu === group.key && (
                  <div className="ge-seg__menu" role="menu">
                    <button
                      type="button"
                      role="menuitem"
                      className="ge-seg__menu-item ge-seg__menu-item--all"
                      onClick={() => pick(null, group.key)}
                    >
                      <span>
                        {t('page.orders.whole_phase', { phase: group.name })}
                      </span>
                      <span className="ge-seg__count">{group.count}</span>
                    </button>
                    {group.filters.map((filter) => (
                      <button
                        key={filter.code ?? group.key}
                        type="button"
                        role="menuitem"
                        className="ge-seg__menu-item"
                        onClick={() => pick(filter.code, null)}
                      >
                        <span>{filter.name}</span>
                        <span className="ge-seg__count">{filter.count}</span>
                      </button>
                    ))}
                  </div>
                )}
              </div>
            );
          })}
        </nav>

        {/* „Moje" zaweza cala liste razem z licznikami przy zakladkach —
            inaczej czerwona liczba mowilaby o zleceniach, ktorych
            w wierszach nie ma. Nie odbiera przy tym niczyjego widoku:
            wylaczony przelacznik to dalej wszystkie zlecenia firmy. */}
        <label className="ge-toggle">
          <input
            type="checkbox"
            checked={mine}
            onChange={(event) => {
              setMine(event.target.checked);
              void load(query, status, event.target.checked, 1);
            }}
          />
          <span className="ge-toggle__track" />
          {t('page.orders.mine')}
        </label>

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
        empty={bands.length === 0 ? emptyLabel : undefined}
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

        {bands.length === 0 && <div className="ge-empty">{emptyLabel}</div>}
      </DataList>

      {summary && summary.total > 0 && (
        <nav className="ge-pager" aria-label={t('page.orders.pager')}>
          <div className="ge-pager__size">
            <span>{t('page.orders.per_page')}</span>
            {[50, 100, 200].map((size) => (
              <button
                key={size}
                type="button"
                className={
                  size === summary.per_page
                    ? 'ge-pager__option is-active'
                    : 'ge-pager__option'
                }
                aria-pressed={size === summary.per_page}
                onClick={() => void changePerPage(size)}
              >
                {size}
              </button>
            ))}
          </div>
          <Button
            variant="text"
            disabled={loading || summary.page <= 1}
            onClick={() => void load(query, status, mine, summary.page - 1)}
          >
            {t('page.orders.prev')}
          </Button>
          <span className="ge-pager__where">
            {t('page.orders.page_of', {
              page: summary.page,
              pages: summary.pages,
            })}
          </span>
          <Button
            variant="text"
            disabled={loading || summary.page >= summary.pages}
            onClick={() => void load(query, status, mine, summary.page + 1)}
          >
            {t('page.orders.next')}
          </Button>
        </nav>
      )}
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

/**
 * Zakładka paska: nazwa, liczba zleceń i czerwona liczba alertów.
 * Pusta zostaje samą etykietą — zero trzeba przeczytać, żeby dowiedzieć
 * się tego samego.
 */
function SegTab({
  name,
  count,
  alerts,
  active,
  alertsTitle,
  onClick,
}: {
  name: string;
  count: number;
  alerts: number;
  active: boolean;
  alertsTitle: string;
  onClick: () => void;
}) {
  return (
    <button
      type="button"
      className={[
        'ge-seg__item',
        active ? 'is-active' : '',
        count === 0 && !active ? 'ge-seg__item--empty' : '',
      ]
        .filter(Boolean)
        .join(' ')}
      aria-pressed={active}
      onClick={onClick}
    >
      <span>{name}</span>
      {count > 0 && <span className="ge-seg__count">{count}</span>}
      {alerts > 0 && (
        <span className="ge-seg__alerts" title={alertsTitle}>
          {alerts}
        </span>
      )}
    </button>
  );
}
