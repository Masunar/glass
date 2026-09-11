import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Link } from 'react-router';

import { Button } from '@salvon/components/button';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { notifyError } from '@salvon/utils/notify';

import type {
  ProductionBoard,
  ProductionIssue,
  ProductionRow,
} from '@app/api/ProductionApi';
import { ProductionApi } from '@app/api/ProductionApi';

const ISSUES: ProductionIssue[] = ['breakage', 'material', 'drawing', 'rework'];

/** Klawisz zadziała tylko poza polem tekstowym — inaczej „p" otwierałoby okno problemu w trakcie pisania. */
const isTyping = (target: EventTarget | null): boolean =>
  target instanceof HTMLElement &&
  (target.tagName === 'INPUT' ||
    target.tagName === 'TEXTAREA' ||
    target.isContentEditable);

/**
 * Kolejka stanowiska.
 *
 * Ekran dla człowieka przy maszynie, nie dla szefa produkcji: bez cen,
 * posortowany po pilności, z parametrem etapu przy pozycji. Obsługa
 * z klawiatury, bo stanowisko ma monitor i klawiaturę, nie tablet —
 * strzałki wybierają pozycję, Enter ją odhacza.
 */
export default function Page() {
  const t = useTranslation();

  const [board, setBoard] = useState<ProductionBoard | null>(null);
  const [station, setStation] = useState<string>('');
  const [done, setDone] = useState(false);
  const [cursor, setCursor] = useState(0);
  const [issueFor, setIssueFor] = useState<number | null>(null);
  const [issueType, setIssueType] = useState<ProductionIssue>('breakage');
  const [issueNote, setIssueNote] = useState('');
  const [busy, setBusy] = useState(false);
  const listRef = useRef<HTMLDivElement | null>(null);

  const load = useCallback(async () => {
    const { content } = await ProductionApi.board(station, done);
    const data: ProductionBoard | undefined = content?.data;

    if (data) {
      setBoard(data);
      setCursor((current) =>
        Math.min(current, Math.max(0, data.rows.length - 1)),
      );
    }
  }, [station, done]);

  useEffect(() => {
    void load();
  }, [load]);

  const rows = useMemo(() => board?.rows ?? [], [board]);
  const current = rows[cursor] ?? null;

  const act = useCallback(
    async (row: ProductionRow, action: 'start' | 'finish' | 'reopen') => {
      setBusy(true);

      const { content, response } =
        action === 'start'
          ? await ProductionApi.start(row.id)
          : action === 'finish'
            ? await ProductionApi.finish(row.id)
            : await ProductionApi.reopen(row.id);

      setBusy(false);

      if (!response.success) {
        notifyError(content?.errors?.task?.[0] ?? t('api.ise'));

        return;
      }

      await load();
    },
    [load, t],
  );

  useEffect(() => {
    const onKey = (event: KeyboardEvent) => {
      if (isTyping(event.target) || event.ctrlKey || event.metaKey) {
        return;
      }

      if (event.key === 'ArrowDown') {
        event.preventDefault();
        setCursor((index) => Math.min(index + 1, rows.length - 1));
      } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        setCursor((index) => Math.max(index - 1, 0));
      } else if (event.key === 'Enter' && current) {
        event.preventDefault();
        void act(current, current.status === 'done' ? 'reopen' : 'finish');
      } else if ((event.key === 's' || event.key === 'S') && current) {
        void act(current, 'start');
      } else if ((event.key === 'p' || event.key === 'P') && current) {
        event.preventDefault();
        setIssueFor(current.id);
        setIssueNote('');
      }
    };

    window.addEventListener('keydown', onKey);

    return () => window.removeEventListener('keydown', onKey);
  }, [rows.length, current, act]);

  useEffect(() => {
    listRef.current
      ?.querySelectorAll('.ge-task')
      ?.[cursor]?.scrollIntoView({ block: 'nearest' });
  }, [cursor]);

  if (!board) {
    return <div className="ge-empty">{t('page.production.loading')}</div>;
  }

  const saveIssue = async (row: ProductionRow) => {
    setBusy(true);
    const { content, response } = await ProductionApi.issue(
      row.id,
      issueType,
      issueNote,
    );
    setBusy(false);

    if (!response.success) {
      notifyError(
        content?.errors?.note?.[0] ??
          content?.errors?.issue_type?.[0] ??
          t('api.ise'),
      );

      return;
    }

    setIssueFor(null);
    setIssueNote('');
    await load();
  };

  return (
    <>
      <header className="ge-head">
        <div>
          <div className="ge-head__kicker">{t('page.production.kicker')}</div>
          <h1 className="ge-head__title">{t('page.production.title')}</h1>
          <div className="ge-quiet">
            {t('page.production.count', { count: board.summary.shown })}
            {board.summary.overdue > 0
              ? ` · ${t('page.production.overdue', { count: board.summary.overdue })}`
              : ''}
            {board.summary.problems > 0
              ? ` · ${t('page.production.problems', { count: board.summary.problems })}`
              : ''}
          </div>
        </div>
      </header>

      <nav className="ge-filters" aria-label={t('page.production.stations')}>
        {[
          { key: '', label: t('page.production.all_stations'), open: null },
          ...board.workstations.map((row) => ({
            key: row.id === null ? 'none' : String(row.id),
            label: row.name ?? t('page.production.no_station'),
            open: row.open,
          })),
        ].map((tab) =>
          tab.key === station ? (
            <span className="ge-filters__here" key={tab.key}>
              {tab.label}
              {tab.open === null ? '' : ` ${tab.open}`}
            </span>
          ) : (
            <button
              type="button"
              key={tab.key}
              onClick={() => {
                setStation(tab.key);
                setCursor(0);
              }}
            >
              {tab.label}
              {tab.open === null ? '' : ` ${tab.open}`}
            </button>
          ),
        )}
        <span className="ge-filters__end">
          <label className="ge-check">
            <input
              type="checkbox"
              checked={done}
              onChange={(event) => setDone(event.target.checked)}
            />
            {t('page.production.show_done')}
          </label>
        </span>
      </nav>

      <div className="ge-card">
        <div className="ge-card__main" ref={listRef}>
          {rows.length === 0 && (
            <div className="ge-quiet">{t('page.production.empty')}</div>
          )}

          {rows.map((row, index) => (
            <div
              className={[
                'ge-task',
                index === cursor ? 'is-current' : '',
                row.status === 'problem' ? 'ge-task--stuck' : '',
                row.status === 'done' ? 'ge-task--done' : '',
                row.days_left !== null && row.days_left < 0
                  ? 'ge-task--late'
                  : '',
              ]
                .filter(Boolean)
                .join(' ')}
              key={row.id}
              onClick={() => setCursor(index)}
            >
              <span className="ge-task__when">
                {row.days_left === null
                  ? t('page.orders.no_deadline')
                  : row.days_left < 0
                    ? t('page.production.late_by', { count: -row.days_left })
                    : t('page.production.in_days', { count: row.days_left })}
                <span className="ge-quiet">{row.deadline ?? ''}</span>
              </span>

              <span className="ge-task__what">
                <span>
                  <Link to={`/orders/${row.order_id}`}>
                    #{row.order_number}
                  </Link>{' '}
                  {row.item ?? '—'}
                </span>
                <span className="ge-quiet">
                  {row.process ?? '—'}
                  {row.width_mm && row.height_mm
                    ? ` · ${row.width_mm} × ${row.height_mm} mm`
                    : ''}
                  {row.quantity ? ` · ${Number(row.quantity)} szt.` : ''}
                  {row.is_irregular_shape
                    ? ` · ${t('page.orders.card.irregular')}`
                    : ''}
                </span>
              </span>

              {/* Parametr stoi przy pozycji, a nie w zakladce obok:
                  to po niego operator szedl dotad do biura. */}
              <span className="ge-task__param">{row.parameter ?? ''}</span>

              <span className="ge-task__state">
                {t(`page.production.status.${row.status}`)}
                {row.by ? <span className="ge-quiet">{row.by}</span> : null}
              </span>
            </div>
          ))}
        </div>

        <aside className="ge-card__side ge-card__side--right">
          {current === null ? (
            <section className="ge-section">
              <div className="ge-quiet">{t('page.production.pick')}</div>
            </section>
          ) : (
            <>
              <section className="ge-section">
                <div className="ge-section__head ge-section__head--strong">
                  #{current.order_number} · {current.process ?? '—'}
                </div>

                <div className="ge-section__body">
                  <div className="ge-from__row">
                    <span>{t('page.production.item')}</span>
                    <span>{current.item ?? '—'}</span>
                  </div>
                  {current.width_mm !== null && current.height_mm !== null && (
                    <div className="ge-from__row">
                      <span>{t('page.production.size')}</span>
                      <span>
                        {current.width_mm} × {current.height_mm} mm
                      </span>
                    </div>
                  )}
                  <div className="ge-from__row">
                    <span>{t('page.production.quantity')}</span>
                    <span>{Number(current.quantity ?? 0)}</span>
                  </div>
                  {current.parameter && (
                    <div className="ge-from__row ge-from__row--off">
                      <span>{t('page.production.parameter')}</span>
                      <span>{current.parameter}</span>
                    </div>
                  )}
                  <div className="ge-from__row">
                    <span>{t('page.production.station')}</span>
                    <span>
                      {current.workstation ?? t('page.production.no_station')}
                    </span>
                  </div>
                </div>
              </section>

              {(current.comment || current.list_comment) && (
                <section className="ge-section">
                  <div className="ge-section__head">
                    {t('page.production.comment')}
                  </div>
                  <div className="ge-section__body">
                    {current.comment && <div>{current.comment}</div>}
                    {current.list_comment && (
                      <div className="ge-quiet">{current.list_comment}</div>
                    )}
                  </div>
                </section>
              )}

              <section className="ge-section">
                <div className="ge-section__body ge-task__actions">
                  {current.status === 'done' ? (
                    <>
                      <div className="ge-quiet">
                        {t('page.production.done_at', {
                          when: current.finished_at ?? '—',
                          who: current.by ?? '—',
                        })}
                        {current.minutes_spent !== null
                          ? ` · ${t('page.production.took', { minutes: current.minutes_spent })}`
                          : ''}
                      </div>
                      <Button
                        variant="outlined"
                        size="small"
                        disabled={busy}
                        onClick={() => void act(current, 'reopen')}
                      >
                        {t('page.production.reopen')}
                      </Button>
                    </>
                  ) : (
                    <>
                      {current.status !== 'in_progress' && (
                        <Button
                          variant="outlined"
                          size="small"
                          disabled={busy}
                          onClick={() => void act(current, 'start')}
                        >
                          {t('page.production.start')}
                        </Button>
                      )}
                      <Button
                        variant="contained"
                        size="small"
                        disabled={busy}
                        onClick={() => void act(current, 'finish')}
                      >
                        {t('page.production.finish')}
                      </Button>
                      <Button
                        variant="outlined"
                        size="small"
                        disabled={busy}
                        onClick={() => {
                          setIssueFor(current.id);
                          setIssueNote('');
                        }}
                      >
                        {t('page.production.report')}
                      </Button>
                    </>
                  )}

                  {current.status === 'problem' && current.note && (
                    <div className="ge-warn">
                      {t(
                        `page.production.issue.${current.issue_type ?? 'rework'}`,
                      )}
                      {' — '}
                      {current.note}
                    </div>
                  )}

                  {/* Czas mierzony od pierwszego "zacznij". To zegar
                      scienny, nie czas pracy — obejmuje przerwe i noc. */}
                  {current.started_at && current.status !== 'done' && (
                    <div className="ge-quiet">
                      {t('page.production.started_at', {
                        when: current.started_at,
                      })}
                    </div>
                  )}

                  {issueFor === current.id && (
                    <div className="ge-task__issue">
                      <label className="ge-uf">
                        <span className="ge-uf__label">
                          {t('page.production.issue_type')}
                        </span>
                        <select
                          className="ge-uf__input ge-uf__select"
                          value={issueType}
                          onChange={(event) =>
                            setIssueType(event.target.value as ProductionIssue)
                          }
                        >
                          {ISSUES.map((key) => (
                            <option key={key} value={key}>
                              {t(`page.production.issue.${key}`)}
                            </option>
                          ))}
                        </select>
                      </label>

                      <label className="ge-uf">
                        <span className="ge-uf__label">
                          {t('page.production.issue_note')}
                        </span>
                        <input
                          className="ge-uf__input"
                          value={issueNote}
                          autoFocus
                          placeholder={t('page.production.issue_hint')}
                          onChange={(event) => setIssueNote(event.target.value)}
                        />
                      </label>

                      <Button
                        variant="contained"
                        size="small"
                        disabled={busy}
                        onClick={() => void saveIssue(current)}
                      >
                        {t('page.production.issue_save')}
                      </Button>
                      <Button
                        variant="outlined"
                        size="small"
                        onClick={() => setIssueFor(null)}
                      >
                        {t('page.production.cancel')}
                      </Button>
                    </div>
                  )}
                </div>
              </section>

              <section className="ge-section">
                <div className="ge-quiet">{t('page.production.keys')}</div>
              </section>
            </>
          )}
        </aside>
      </div>
    </>
  );
}
