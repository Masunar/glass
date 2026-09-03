import {
  type KeyboardEvent,
  useEffect,
  useMemo,
  useRef,
  useState,
} from 'react';
import { PiMagnifyingGlass } from 'react-icons/pi';
import { useNavigate } from 'react-router';

import { useTranslation } from '@salvon/hooks/useTranslation';
import { isMac } from '@salvon/utils/operating-system';

import { SearchApi, type SearchGroup } from '@app/api/SearchApi';
import { appModules } from '@app/config/modules';
import { useHasPermission } from '@app/hook/use-permissions';

type Row = {
  key: string;
  title: string;
  subtitle: string;
  path: string;
  module: string;
  /** Pierwszy wiersz grupy niesie jej nagłówek. */
  groupLabel?: string;
  groupCount?: number;
};

type Props = {
  open: boolean;
  onClose: () => void;
};

/**
 * Wyszukiwarka ogólna — jedno pole na całą aplikację.
 *
 * Stary system miał osobne wyszukiwanie na każdym ekranie, więc szukając
 * numeru trzeba było najpierw wiedzieć, gdzie ten numer mieszka. Tutaj
 * pytanie idzie do wszystkiego naraz, a kolor grupy to ten sam kolor,
 * co na listwie modułów — wynik mówi, skąd pochodzi, zanim się go
 * przeczyta.
 *
 * Ekrany pasujące do zapytania stoją nad danymi: „cennik" ma prowadzić
 * do cennika, a nie do produktu o tej nazwie.
 */
export default function Spotlight({ open, onClose }: Props) {
  const t = useTranslation();
  const navigate = useNavigate();
  const hasPermissionTo = useHasPermission();
  const input = useRef<HTMLInputElement>(null);
  const list = useRef<HTMLDivElement>(null);

  const [query, setQuery] = useState('');
  const [groups, setGroups] = useState<SearchGroup[]>([]);
  const [active, setActive] = useState(0);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (!open) {
      return;
    }

    setQuery('');
    setGroups([]);
    setActive(0);
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
        setActive(0);
        setLoading(false);
      })();
    }, 220);

    return () => clearTimeout(timer);
  }, [query, open]);

  /** Ekrany aplikacji dopasowane po nazwie — bez pytania serwera. */
  const screens = useMemo<Row[]>(() => {
    const needle = query.trim().toLowerCase();

    if (needle.length < SearchApi.minLength) {
      return [];
    }

    const rows: Row[] = [];

    for (const module of appModules) {
      for (const link of module.links) {
        if (link.path === undefined) {
          continue;
        }

        if (link.permission && !hasPermissionTo([link.permission])) {
          continue;
        }

        const label = t(link.labelKey);

        if (!label.toLowerCase().includes(needle)) {
          continue;
        }

        rows.push({
          key: `screen:${link.path}`,
          title: label,
          subtitle: t(module.labelKey),
          path: link.path,
          module: module.key,
        });
      }
    }

    return rows;
  }, [query, t, hasPermissionTo]);

  const rows = useMemo<Row[]>(() => {
    const fromScreens = screens.map((row, index) => ({
      ...row,
      groupLabel: index === 0 ? t('page.search.screens') : undefined,
      groupCount: index === 0 ? screens.length : undefined,
    }));

    const fromData = groups.flatMap((group) =>
      group.hits.map((hit, index) => ({
        key: `${group.key}:${hit.id}`,
        title: hit.title,
        subtitle: hit.subtitle,
        path: hit.path,
        module: group.module,
        groupLabel: index === 0 ? group.label : undefined,
        groupCount: index === 0 ? group.hits.length : undefined,
      })),
    );

    return [...fromScreens, ...fromData];
  }, [screens, groups, t]);

  const open_ = (row: Row) => {
    onClose();
    void navigate(row.path);
  };

  const handleKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
    if (event.key === 'Escape') {
      event.preventDefault();
      onClose();
      return;
    }

    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      event.preventDefault();

      if (rows.length === 0) {
        return;
      }

      const step = event.key === 'ArrowDown' ? 1 : -1;
      const next = (active + step + rows.length) % rows.length;

      setActive(next);

      const hits = list.current?.querySelectorAll('.ge-spot__hit');

      hits?.item(next)?.scrollIntoView({ block: 'nearest' });
      return;
    }

    if (event.key === 'Enter' && rows[active]) {
      event.preventDefault();
      open_(rows[active]);
    }
  };

  const tooShort = query.trim().length < SearchApi.minLength;

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
      >
        <div className="ge-spot__field">
          <span className="ge-spot__icon">
            <PiMagnifyingGlass size={20} />
          </span>
          <input
            ref={input}
            className="ge-spot__input"
            type="text"
            value={query}
            placeholder={t('page.search.placeholder')}
            aria-label={t('page.search.title')}
            onChange={(event) => setQuery(event.target.value)}
          />
        </div>

        <div className="ge-spot__body" ref={list}>
          {rows.map((row, index) => (
            <div
              key={row.key}
              style={{ ['--ge-grp' as string]: `var(--m-${row.module})` }}
            >
              {row.groupLabel && (
                <div className="ge-spot__group">
                  {row.groupLabel}
                  <span className="ge-spot__count">{row.groupCount}</span>
                </div>
              )}
              <button
                type="button"
                className={
                  index === active ? 'ge-spot__hit is-active' : 'ge-spot__hit'
                }
                onClick={() => open_(row)}
                onMouseEnter={() => setActive(index)}
              >
                <span className="ge-spot__title">{row.title}</span>
                {row.subtitle && (
                  <span className="ge-spot__sub">{row.subtitle}</span>
                )}
              </button>
            </div>
          ))}

          {rows.length === 0 && (
            <div className="ge-spot__empty">
              {tooShort
                ? t('page.search.hint', { count: SearchApi.minLength })
                : loading
                  ? t('page.search.searching')
                  : t('page.search.empty', { query: query.trim() })}
            </div>
          )}
        </div>

        <div className="ge-spot__foot">
          <span>
            <span className="ge-spot__key">↑</span>
            <span className="ge-spot__key">↓</span>
            {t('page.search.navigate')}
          </span>
          <span>
            <span className="ge-spot__key">↵</span>
            {t('page.search.open')}
          </span>
          <span>
            <span className="ge-spot__key">esc</span>
            {t('page.search.close')}
          </span>
          <span className="ge-spot__foot-end">
            {t('page.search.results', { count: rows.length })}
          </span>
        </div>
      </div>
    </>
  );
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
