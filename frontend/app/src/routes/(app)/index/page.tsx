import { useEffect, useMemo, useRef, useState } from 'react';
import { PiArrowRight } from 'react-icons/pi';
import { Link, useNavigate } from 'react-router';

import { Button } from '@salvon/components/button';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { notifyError } from '@salvon/utils/notify';

import type {
  DashboardAlert,
  DashboardBoard,
  DashboardTask,
  TaskBand,
} from '@app/api/DashboardApi';
import { DashboardApi } from '@app/api/DashboardApi';
import { OrdersApi } from '@app/api/OrdersApi';
import { ListWait } from '@app/components/list';

type Filter = 'all' | TaskBand;

/**
 * Pulpit — jedna lista spraw, pas liczb, blokady i braki obok.
 *
 * **Sprawa to zlecenie, z którym da się coś teraz zrobić albo które
 * się spóźnia.** Pierwsza na liście dostaje prawdziwy przycisk i wraca
 * w nagłówku jako „zacznij od #24004": ekran ma dać jeden punkt
 * wejścia, a nie siedem równorzędnych.
 *
 * **Akcja wykonuje się z wiersza.** To ta sama droga, co kolumna „co
 * dalej" na liście zleceń — po przejściu wiersz zmienia pasmo, więc
 * pulpit wczytuje się od nowa.
 *
 * Kreska zamiast liczby znaczy **brak dostępu**, nie zero. Sekcja, do
 * której brakuje uprawnień, w ogóle nie przychodzi z serwera.
 */
export default function Page() {
  const t = useTranslation();
  const navigate = useNavigate();
  const [board, setBoard] = useState<DashboardBoard | null>(null);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState<Filter>('all');
  const [busy, setBusy] = useState<number | null>(null);
  // `null` — jeszcze nie przyszly. Pusta lista znaczy co innego: nic
  // sie nie pali. Tych dwoch stanow nie wolno pokazac tak samo.
  const [alerts, setAlerts] = useState<DashboardAlert[] | null>(null);
  const [alertsFailed, setAlertsFailed] = useState(false);
  // Numer wczytania: odpowiedz z poprzedniego, spozniona, nie moze
  // nadpisac nowszej.
  const round = useRef(0);

  /**
   * Pulpit i pasmo alertów naraz, dwoma zapytaniami. Silnik alertów
   * liczy się przy odczycie — gdyby szedł w tym samym zapytaniu, cały
   * pulpit czekałby na niego, zanim pokazałby choć jedną sprawę.
   */
  const load = async () => {
    const ticket = ++round.current;

    setLoading(true);
    setAlerts(null);
    setAlertsFailed(false);

    void DashboardApi.alerts().then(({ content }) => {
      if (ticket !== round.current) {
        return;
      }

      const data: { alerts: DashboardAlert[] } | undefined = content?.data;

      if (data) {
        setAlerts(data.alerts);
      } else {
        setAlertsFailed(true);
      }
    });

    const { content } = await DashboardApi.board();
    const data: DashboardBoard | undefined = content?.data;

    if (ticket !== round.current) {
      return;
    }

    setLoading(false);

    if (data) {
      setBoard(data);
    }
  };

  useEffect(() => {
    void load();
  }, []);

  const tasks = useMemo(
    () =>
      (board?.tasks ?? []).filter(
        (task) => filter === 'all' || task.band === filter,
      ),
    [board, filter],
  );

  if (!board) {
    return (
      <>
        <ListWait on={loading} />
        {!loading && <div className="ge-empty">{t('page.home.failed')}</div>}
      </>
    );
  }

  const run = async (task: DashboardTask) => {
    if (task.next_step === null) {
      void navigate(`/orders/${task.id}`);

      return;
    }

    setBusy(task.id);

    const { content, response } = await OrdersApi.transition(
      task.id,
      task.next_step.transition_id,
    );

    setBusy(null);

    if (!response.success) {
      notifyError(
        content?.errors?.transition?.[0] ??
          t('page.orders.transition_failed', { number: task.number }),
      );

      return;
    }

    await load();
  };

  const { summary, counters, top, blocked, shortages } = board;
  // Bez przecinka miedzy dniem tygodnia a data: „sroda 23 wrzesnia"
  // czyta sie jak nadtytul, „sroda, 23 wrzesnia" jak zdanie.
  const day = new Date(board.as_of)
    .toLocaleDateString('pl-PL', {
      weekday: 'long',
      day: 'numeric',
      month: 'long',
    })
    .replace(',', '');

  return (
    <div className="ge-home-screen">
      <header className="ge-head ge-home__head">
        <div>
          <div className="ge-head__kicker">
            {[day, board.user.location].filter(Boolean).join(' · ')}
          </div>
          <h1 className="ge-head__title">
            {t('page.home.greeting', { name: board.user.name })}
          </h1>
          <div className="ge-quiet">
            {summary.tasks === 0
              ? t('page.home.lead_clear')
              : summary.overdue > 0
                ? t('page.home.lead_overdue', {
                    tasks: summary.tasks,
                    overdue: summary.overdue,
                  })
                : t('page.home.lead', { tasks: summary.tasks })}
          </div>
        </div>

        {top !== null && (
          <div className="ge-head__actions">
            <Button
              variant="contained"
              loading={busy === top.id}
              onClick={() => void run(top)}
            >
              {t('page.home.start_with', { number: top.number })}
            </Button>
          </div>
        )}
      </header>

      {/* Pas liczb: kreska zamiast zera znaczy „nie masz do tego
          dostepu". Zero i brak uprawnienia musza wygladac inaczej. */}
      <div className="ge-kpis">
        <Kpi
          label={t('page.home.kpi.overdue')}
          value={counters.overdue}
          tone="alert"
        />
        <Kpi
          label={t('page.home.kpi.today')}
          value={counters.today}
          tone="mod"
        />
        <Kpi
          label={t('page.home.kpi.shortages')}
          value={counters.shortages}
          tone="money"
        />
        <Kpi
          label={t('page.home.kpi.production')}
          value={counters.production}
          tone="prod"
        />
        <Kpi
          label={t('page.home.kpi.furnace')}
          value={counters.furnace}
          tone="prod"
        />
        <Kpi
          label={t('page.home.kpi.offers')}
          value={counters.offers}
          tone="plain"
        />
      </div>

      <div className="ge-home">
        <div className="ge-home__main">
          <div className="ge-segbar">
            <nav
              className="ge-seg ge-seg--filter"
              aria-label={t('page.home.tasks')}
            >
              {(['all', 'overdue', 'today', 'later'] as Filter[]).map((key) => {
                const count =
                  key === 'all' ? summary.tasks : summary[key as TaskBand];
                const here = filter === key;

                return (
                  <button
                    key={key}
                    type="button"
                    className={[
                      'ge-seg__item',
                      here ? 'is-active' : '',
                      count === 0 && !here ? 'ge-seg__item--empty' : '',
                    ]
                      .filter(Boolean)
                      .join(' ')}
                    aria-pressed={here}
                    onClick={() => setFilter(key)}
                  >
                    <span>{t(`page.home.filter.${key}`)}</span>
                    {count > 0 && (
                      <span className="ge-seg__count">{count}</span>
                    )}
                  </button>
                );
              })}
            </nav>
          </div>

          <ListWait on={loading} />

          {tasks.length === 0 ? (
            <div className="ge-list__empty">{t('page.home.tasks_empty')}</div>
          ) : (
            tasks.map((task, index) => (
              <div className={`ge-task ge-task--${task.band}`} key={task.id}>
                <Link to={`/orders/${task.id}`} className="ge-task__number">
                  #{task.number}
                </Link>
                <span className="ge-task__party">
                  <span className="ge-task__name">
                    {task.contractor ?? '—'}
                  </span>
                  {/* Inicjaly tylko przy cudzych sprawach: wlasne sa na
                      gorze i podpisywanie ich swoim nazwiskiem niczego
                      nie mowi. Cudza sprawa zostaje na liscie — pulpit
                      mowi, co pilne, a nie co czyje — wiec musi byc
                      widac, kogo o nia zapytac. */}
                  {!task.is_mine && task.owner_initials && (
                    <span
                      className="ge-avatar ge-task__who"
                      title={task.owner ?? ''}
                    >
                      {task.owner_initials}
                    </span>
                  )}
                </span>
                <span
                  className={
                    task.band === 'overdue'
                      ? 'ge-task__due is-late'
                      : 'ge-task__due'
                  }
                >
                  {task.deadline_label ?? ''}
                </span>

                {/* Pierwszy wiersz dostaje prawdziwy przycisk, reszta
                    odnosnik: ekran proponuje jeden start, nie siedem. */}
                {index === 0 && filter === 'all' ? (
                  <Button
                    variant="contained"
                    size="small"
                    loading={busy === task.id}
                    onClick={() => void run(task)}
                  >
                    {task.next_step?.label ?? t('page.home.open')}
                  </Button>
                ) : (
                  <button
                    type="button"
                    className="ge-task__action"
                    disabled={busy === task.id}
                    onClick={() => void run(task)}
                  >
                    {task.next_step?.label ?? t('page.home.open')}{' '}
                    <PiArrowRight />
                  </button>
                )}
              </div>
            ))
          )}
        </div>

        <aside className="ge-home__side">
          {/* Pasmo w drodze: naglowek i pasek pobierania w jego miejscu,
              zeby nic nie przeskoczylo, gdy dojdzie. Bez tego brak pasma
              wygladalby jak „brak alertow". */}
          {alerts === null && (
            <section className="ge-home__box" aria-busy="true">
              <div className="ge-home__box-head">{t('page.home.alerts')}</div>
              <ListWait on={!alertsFailed} />
              <div className="ge-quiet">
                {t(
                  alertsFailed
                    ? 'page.home.alerts_failed'
                    : 'page.home.alerts_loading',
                )}
              </div>
            </section>
          )}

          {alerts !== null && alerts.length > 0 && (
            <section className="ge-home__box">
              <div className="ge-home__box-head">
                {t('page.home.alerts')}
                <span className="ge-home__box-count">
                  {alerts.reduce((sum, alert) => sum + alert.count, 0)}
                </span>
              </div>

              {alerts.map((alert) => (
                <div className="ge-home__alert" key={alert.code}>
                  <span
                    className="ge-home__alert-name"
                    style={
                      alert.color
                        ? ({ '--ge-mark': alert.color } as React.CSSProperties)
                        : undefined
                    }
                    title={alert.name}
                  >
                    {alert.label}
                  </span>
                  <span className="ge-home__alert-count">{alert.count}</span>

                  {/* Podpisy sa odnosnikami: liczba bez miejsca, w ktore
                      mozna z nia pojsc, kaze szukac jej recznie. Podpis
                      bez ekranu zostaje samym tekstem — odnosnik
                      donikad byłby gorszy niz jego brak. */}
                  <span className="ge-home__alert-orders">
                    {alert.subjects.map((subject, index) => {
                      /* Inicjaly tylko przy cudzej sprawie: wlasne stoja
                         pierwsze, a podpisywanie ich swoim nazwiskiem
                         niczego nie mowi. Rzecz bez wlasciciela — towar,
                         partia w piecu — nie dostaje ich wcale. */
                      const who =
                        !subject.is_mine && subject.owner_initials ? (
                          <span
                            className="ge-home__alert-who"
                            title={subject.owner ?? ''}
                          >
                            {subject.owner_initials}
                          </span>
                        ) : null;

                      return subject.path === null ? (
                        <span key={`${subject.label}-${index}`}>
                          {subject.label}
                          {who}
                        </span>
                      ) : (
                        <Link
                          key={`${subject.label}-${index}`}
                          to={subject.path}
                          className="ge-link"
                        >
                          {subject.label}
                          {who}
                        </Link>
                      );
                    })}
                  </span>
                </div>
              ))}
            </section>
          )}

          {blocked.length > 0 && (
            <section className="ge-home__box">
              <div className="ge-home__box-head">{t('page.home.blocked')}</div>

              {blocked.map((item) => (
                <div className="ge-home__blocked" key={item.reason}>
                  <strong>
                    {t('page.home.blocked_count', { count: item.count })}
                  </strong>{' '}
                  <span className="ge-quiet">{item.reason}</span>{' '}
                  <Link to="/orders" className="ge-link">
                    {t('page.home.show')}
                  </Link>
                </div>
              ))}
            </section>
          )}

          {shortages !== null && shortages.rows.length > 0 && (
            <section className="ge-home__box">
              <div className="ge-home__box-head">
                {t('page.home.shortages')}
                <span className="ge-home__box-count">{shortages.total}</span>
              </div>

              {shortages.rows.map((row) => (
                <div className="ge-home__stock" key={row.product_id}>
                  <span className="ge-home__stock-name">{row.name}</span>
                  <span className="ge-home__stock-ratio">
                    {row.available} / {row.max}
                  </span>
                  {/* Pasek, nie liczba sama: „0 z 12" i „10 z 12" czyta
                      sie jednym spojrzeniem dopiero w kolumnie. */}
                  <span className="ge-home__bar">
                    <span
                      style={{
                        width: `${row.max > 0 ? Math.min(100, Math.max(0, (row.available / row.max) * 100)) : 0}%`,
                      }}
                    />
                  </span>
                </div>
              ))}

              <div className="ge-home__box-foot">
                <Link to="/magazyn" className="ge-link">
                  {t('page.home.all_shortages')} <PiArrowRight />
                </Link>
              </div>
            </section>
          )}
        </aside>
      </div>
    </div>
  );
}

/** Licznik w pasie. `null` to kreska — brak dostępu, nie zero. */
function Kpi({
  label,
  value,
  tone,
}: {
  label: string;
  value: number | null;
  tone: 'alert' | 'mod' | 'money' | 'prod' | 'plain';
}) {
  return (
    <div className="ge-kpi">
      <div className="ge-kpi__label">{label}</div>
      <div
        className={
          value === null || value === 0
            ? 'ge-kpi__value is-none'
            : `ge-kpi__value ge-kpi__value--${tone}`
        }
      >
        {/* Kreska i przy braku dostepu, i przy zerze: na pulpicie oba
            znacza „nie ma sie tu czym zajmowac". Rozroznienie zostaje
            po stronie serwera, gdzie decyduje o tym, czy sekcja w ogole
            przychodzi. */}
        {value === null || value === 0 ? '—' : value}
      </div>
    </div>
  );
}
