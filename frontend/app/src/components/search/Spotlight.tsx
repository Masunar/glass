import {
  type KeyboardEvent,
  useCallback,
  useEffect,
  useMemo,
  useRef,
  useState,
} from 'react';
import { PiMagnifyingGlass } from 'react-icons/pi';
import { useNavigate } from 'react-router';

import { useTranslation } from '@salvon/hooks/useTranslation';
import {
  getFromLocalStorage,
  saveToLocalStorage,
} from '@salvon/utils/local-storage';
import { isMac } from '@salvon/utils/operating-system';

import { SearchApi, type SearchGroup } from '@app/api/SearchApi';
import { appModules } from '@app/config/modules';
import { useHasPermission } from '@app/hook/use-permissions';

const RECENT_KEY = 'ge.spotlight.recent';
const RECENT_LIMIT = 4;

type Row = {
  key: string;
  /** Dwie litery zamiast ikony — kod jest krótszy niż nazwa. */
  tile: string;
  title: string;
  meta?: string;
  shortcut?: string;
  path: string;
  module: string;
  scope: string;
  action?: boolean;
  groupLabel?: string;
};

type Recent = {
  key: string;
  tile: string;
  title: string;
  meta?: string;
  path: string;
  module: string;
};

type Scope = { key: string; label: string; module: string };

/** Kody grup — dwie litery, tak jak na listwie modułów. */
const TILES: Record<string, string> = {
  orders: 'ZL',
  contractors: 'KO',
  products: 'CE',
  users: 'UŻ',
  locations: 'LO',
  screen: 'EK',
};

function readRecent(): Recent[] {
  const stored = getFromLocalStorage(RECENT_KEY, []);

  return Array.isArray(stored)
    ? (stored as Recent[]).slice(0, RECENT_LIMIT)
    : [];
}

type Props = {
  open: boolean;
  onClose: () => void;
};

/**
 * Wyszukiwarka ogólna — jedno pole na całą aplikację.
 *
 * Stary system miał osobne wyszukiwanie na każdym ekranie, więc szukając
 * numeru trzeba było najpierw wiedzieć, gdzie ten numer mieszka.
 *
 * Pusty panel nie mówi „wpisz dwa znaki". Pokazuje ostatnio otwarte
 * i szybkie akcje, bo najczęstszy powód otwarcia wyszukiwarki to powrót
 * do czegoś, co się przed chwilą oglądało.
 *
 * Kolor grupy to ten sam kolor, co na listwie modułów — wynik mówi,
 * skąd pochodzi, zanim się go przeczyta.
 */
export default function Spotlight({ open, onClose }: Props) {
  const t = useTranslation();
  const navigate = useNavigate();
  const hasPermissionTo = useHasPermission();
  const input = useRef<HTMLInputElement>(null);
  const list = useRef<HTMLDivElement>(null);

  const [query, setQuery] = useState('');
  const [groups, setGroups] = useState<SearchGroup[]>([]);
  const [scope, setScope] = useState('all');
  const [selected, setSelected] = useState(0);
  const [loading, setLoading] = useState(false);
  const [recent, setRecent] = useState<Recent[]>([]);

  useEffect(() => {
    if (!open) {
      return;
    }

    setQuery('');
    setGroups([]);
    setScope('all');
    setSelected(0);
    setRecent(readRecent());
    input.current?.focus();
  }, [open]);

  // Zapytanie po odstaniu: pisanie „kowalski" to osiem znaków, a nie
  // osiem zapytań do bazy.
  useEffect(() => {
    if (!open || query.trim().length < SearchApi.minLength) {
      setGroups([]);
      setLoading(false);
      return;
    }

    setLoading(true);
    const timer = setTimeout(() => {
      void (async () => {
        const { content } = await SearchApi.query(query.trim());

        setGroups(content?.data?.groups ?? []);
        setSelected(0);
        setLoading(false);
      })();
    }, 220);

    return () => clearTimeout(timer);
  }, [query, open]);

  /** Ekrany aplikacji — dopasowane po nazwie, bez pytania serwera. */
  const screens = useMemo(
    () =>
      appModules.flatMap((module) =>
        module.links
          .filter(
            (link) =>
              link.path !== undefined &&
              (link.permission === undefined ||
                hasPermissionTo([link.permission])),
          )
          .map((link) => ({
            key: `screen:${link.path}`,
            tile: TILES.screen,
            title: t(link.labelKey),
            meta: t(module.labelKey),
            path: link.path ?? '/',
            module: module.key,
            scope: 'screens',
          })),
      ),
    [t, hasPermissionTo],
  );

  const scopes = useMemo<Scope[]>(() => {
    const available: Scope[] = [
      { key: 'all', label: t('page.search.scope.all'), module: 'zlec' },
      { key: 'orders', label: t('page.search.scope.orders'), module: 'zlec' },
      {
        key: 'contractors',
        label: t('page.search.scope.contractors'),
        module: 'zlec',
      },
      {
        key: 'products',
        label: t('page.search.scope.products'),
        module: 'zlec',
      },
      { key: 'screens', label: t('page.search.scope.screens'), module: 'adm' },
      { key: 'users', label: t('page.search.scope.users'), module: 'adm' },
    ];

    return available;
  }, [t]);

  const inScope = useCallback(
    (key: string) => scope === 'all' || scope === key,
    [scope],
  );

  /** Szybkie akcje — tylko takie, które faktycznie dokądś prowadzą. */
  const actions = useMemo<Row[]>(() => {
    const rows: Row[] = [];

    if (
      screens.some((screen) => screen.path === '/contractors') &&
      inScope('contractors')
    ) {
      rows.push({
        key: 'action:new-contractor',
        tile: '+',
        title: t('page.search.action.new_contractor'),
        path: '/contractors?new=1',
        module: 'zlec',
        scope: 'contractors',
        action: true,
      });
    }

    return rows;
  }, [screens, inScope, t]);

  const rows = useMemo<Row[]>(() => {
    const needle = query.trim().toLowerCase();
    const searching = needle.length >= SearchApi.minLength;

    if (!searching) {
      // Stan pusty: ostatnio otwarte i szybkie akcje zamiast instrukcji.
      const recentRows: Row[] = recent
        .filter((item) => inScope(item.key.split(':')[0]))
        .map((item) => ({
          key: `recent:${item.key}`,
          tile: item.tile,
          title: item.title,
          meta: item.meta,
          path: item.path,
          module: item.module,
          scope: item.key.split(':')[0],
        }));

      const screenRows: Row[] = inScope('screens') ? screens : [];

      return [
        ...withGroup(recentRows, t('page.search.group.recent')),
        ...withGroup(
          [...actions, ...screenRows],
          t('page.search.group.actions'),
        ),
      ];
    }

    const screenRows: Row[] = inScope('screens')
      ? screens.filter((screen) => screen.title.toLowerCase().includes(needle))
      : [];

    const dataRows: Row[] = groups
      .filter((group) => inScope(group.key))
      .flatMap((group) =>
        group.hits.map((hit) => ({
          key: `${group.key}:${hit.id}`,
          tile: TILES[group.key] ?? '··',
          title: hit.title,
          meta: hit.subtitle,
          path: hit.path,
          module: group.module,
          scope: group.key,
          groupLabel: group.label,
        })),
      );

    const grouped: Row[] = [];
    let previous: string | null = null;

    for (const row of dataRows) {
      const label = row.groupLabel ?? '';

      grouped.push(
        label === previous ? { ...row, groupLabel: undefined } : row,
      );
      previous = label;
    }

    // Ekrany stoją nad danymi: „cennik" ma prowadzić do cennika, a nie
    // do produktu o tej nazwie.
    return [
      ...withGroup(screenRows, t('page.search.group.screens')),
      ...grouped,
    ];
  }, [query, recent, screens, actions, groups, inScope, t]);

  const choose = (row: Row) => {
    if (!row.action) {
      const entry: Recent = {
        key: row.key.replace(/^recent:/, ''),
        tile: row.tile,
        title: row.title,
        meta: row.meta,
        path: row.path,
        module: row.module,
      };

      const next = [
        entry,
        ...readRecent().filter((item) => item.key !== entry.key),
      ].slice(0, RECENT_LIMIT);

      saveToLocalStorage(RECENT_KEY, next);
    }

    onClose();
    void navigate(row.path);
  };

  const handleKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
    if (event.key === 'Escape') {
      event.preventDefault();
      onClose();
      return;
    }

    // Tab przechodzi po zakresach — filtr modułu bez sięgania po mysz.
    if (event.key === 'Tab') {
      event.preventDefault();

      const index = scopes.findIndex((item) => item.key === scope);
      const step = event.shiftKey ? -1 : 1;

      setScope(scopes[(index + step + scopes.length) % scopes.length].key);
      setSelected(0);
      return;
    }

    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      event.preventDefault();

      if (rows.length === 0) {
        return;
      }

      const step = event.key === 'ArrowDown' ? 1 : -1;
      const next = (selected + step + rows.length) % rows.length;

      setSelected(next);

      const items = list.current?.querySelectorAll('.ge-spot__item');

      items?.item(next)?.scrollIntoView({ block: 'nearest' });
      return;
    }

    if (event.key === 'Enter' && rows[selected]) {
      event.preventDefault();
      choose(rows[selected]);
    }
  };

  const searching = query.trim().length >= SearchApi.minLength;
  const activeScope = scopes.find((item) => item.key === scope) ?? scopes[0];

  return (
    <>
      <div
        className={open ? 'ge-spot__scrim is-open' : 'ge-spot__scrim'}
        onClick={onClose}
        aria-hidden="true"
      />

      <div
        className={open ? 'ge-spot is-open' : 'ge-spot'}
        role="dialog"
        aria-modal="true"
        aria-label={t('page.search.title')}
        inert={!open}
        onKeyDown={handleKeyDown}
        style={{ ['--ge-scp' as string]: `var(--m-${activeScope.module})` }}
      >
        <div className="ge-spot__query">
          <span className="ge-spot__glass">
            <PiMagnifyingGlass size={20} />
          </span>
          <input
            ref={input}
            className="ge-spot__input"
            type="text"
            value={query}
            placeholder={t('page.search.placeholder')}
            aria-label={t('page.search.title')}
            autoComplete="off"
            onChange={(event) => setQuery(event.target.value)}
          />
          <span className="ge-spot__badge">{isMac() ? '⌘ K' : 'CTRL K'}</span>
        </div>

        <div
          className="ge-spot__scopes"
          role="tablist"
          aria-label={t('page.search.scope.label')}
        >
          {scopes.map((item) => (
            <button
              key={item.key}
              type="button"
              role="tab"
              aria-selected={item.key === scope}
              className={
                item.key === scope
                  ? 'ge-spot__scope is-active'
                  : 'ge-spot__scope'
              }
              style={{ ['--ge-scp' as string]: `var(--m-${item.module})` }}
              onClick={() => {
                setScope(item.key);
                setSelected(0);
              }}
            >
              {item.label}
            </button>
          ))}
        </div>

        <div className="ge-spot__body" ref={list}>
          {rows.map((row, index) => (
            <div
              key={row.key}
              style={{ ['--ge-grp' as string]: `var(--m-${row.module})` }}
            >
              {row.groupLabel && (
                <div className="ge-spot__group">{row.groupLabel}</div>
              )}
              <button
                type="button"
                className={
                  index === selected
                    ? 'ge-spot__item is-selected'
                    : 'ge-spot__item'
                }
                onClick={() => choose(row)}
                onMouseEnter={() => setSelected(index)}
              >
                <span
                  className={
                    row.action
                      ? 'ge-spot__tile ge-spot__tile--action'
                      : 'ge-spot__tile'
                  }
                >
                  {row.tile}
                </span>
                <span className="ge-spot__text">
                  <span className="ge-spot__title">{row.title}</span>
                  {row.meta && (
                    <span className="ge-spot__meta">{row.meta}</span>
                  )}
                </span>
                {row.shortcut && (
                  <span className="ge-spot__key">{row.shortcut}</span>
                )}
                {index === selected && !row.shortcut && (
                  <span className="ge-spot__aside">
                    {t('page.search.open')} ↵
                  </span>
                )}
              </button>
            </div>
          ))}

          {rows.length === 0 && (
            <div className="ge-spot__none">
              {loading
                ? t('page.search.searching')
                : searching
                  ? t('page.search.none', { query: query.trim() })
                  : t('page.search.nothing_yet')}
            </div>
          )}
        </div>

        <div className="ge-spot__foot">
          <span>
            <span className="ge-spot__kbd">↑</span>
            <span className="ge-spot__kbd">↓</span>
            {t('page.search.navigate')}
          </span>
          <span>
            <span className="ge-spot__kbd">↵</span>
            {t('page.search.open')}
          </span>
          <span>
            <span className="ge-spot__kbd">tab</span>
            {t('page.search.filter')}
          </span>
          <span className="ge-spot__foot-end">
            <span className="ge-spot__kbd">esc</span>
            {t('page.search.close')}
          </span>
        </div>
      </div>
    </>
  );
}

/** Nagłówek grupy niesie pierwszy wiersz — grupy pustej nie ma. */
function withGroup(rows: Row[], label: string): Row[] {
  return rows.map((row, index) => ({
    ...row,
    groupLabel: index === 0 ? label : undefined,
  }));
}

/** Pole w panelu modułu; samo nie szuka, tylko otwiera wyszukiwarkę. */
export function SpotlightOpener({ onOpen }: { onOpen: () => void }) {
  const t = useTranslation();

  return (
    <button type="button" className="ge-spot__opener" onClick={onOpen}>
      <PiMagnifyingGlass size={15} />
      {t('page.search.placeholder')}
      <span className="ge-spot__shortcut">{isMac() ? '⌘ K' : 'Ctrl K'}</span>
    </button>
  );
}
