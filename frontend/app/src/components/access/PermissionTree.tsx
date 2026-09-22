import { useTranslation } from '@salvon/hooks/useTranslation';

import type { AccessGroup, AccessModule } from '@app/api/AccessApi';

type Props = {
  modules: AccessModule[];
  groups: AccessGroup[];
  /** Zaznaczone teraz — stan formularza, nie stan bazy. */
  checked: Set<string>;
  /**
   * Zaznaczone, ale nie do ruszenia tutaj: przychodzą z paczki albo
   * z roli. Odznaczalne wyglądałyby na odbieranie, którego ten model
   * nie robi — nadania się dokładają.
   */
  locked?: Set<string>;
  disabled?: boolean;
  onToggle: (name: string) => void;
  onToggleGroup: (names: string[], on: boolean) => void;
};

/**
 * Drzewo uprawnień — moduły, potem grupy per zasób.
 *
 * Trzy ekrany pokazują dokładnie to samo drzewo: rola, paczka
 * i użytkownik. Różni je wyłącznie to, co jest zablokowane, więc
 * rozejście się tych widoków byłoby błędem, którego nikt by nie
 * zauważył — ten sam zestaw uprawnień wyglądałby inaczej w zależności
 * od tego, którędy się do niego weszło.
 */
export default function PermissionTree({
  modules,
  groups,
  checked,
  locked,
  disabled = false,
  onToggle,
  onToggleGroup,
}: Props) {
  const t = useTranslation();
  const isLocked = (name: string) => disabled || (locked?.has(name) ?? false);

  return (
    <>
      <section className="ge-section">
        <div className="ge-section__head ge-section__head--strong">
          {t('page.access.modules')}
        </div>
        <div className="ge-quiet">{t('page.access.modules_note')}</div>

        <div className="ge-acc__tiles">
          {modules.map((module) => (
            <label className="ge-acc__tile" key={module.key}>
              <span className="ge-acc__tile-head">
                <input
                  type="checkbox"
                  disabled={isLocked(module.access_permission)}
                  checked={checked.has(module.access_permission)}
                  onChange={() => onToggle(module.access_permission)}
                />
                <strong>{module.label}</strong>
              </span>
              {/* Licznik pokrycia zamiast osiemnastu przelacznikow:
                  „6 z 18" mowi to samo w jednym spojrzeniu. */}
              <span className="ge-quiet">
                {t('page.access.pages_covered', {
                  covered: module.pages_covered,
                  total: module.pages,
                })}
              </span>
              {module.access_origin && (
                <span className="ge-quiet">{module.access_origin}</span>
              )}
            </label>
          ))}
        </div>
      </section>

      {groups.map((group) => (
        <section className="ge-section" key={group.key}>
          <div className="ge-section__head">
            {group.label}
            <span className="ge-section__end ge-quiet">
              <code>{group.key}</code> · {group.granted}/{group.total}
              {!disabled && (
                <button
                  type="button"
                  className="ge-acc__all"
                  onClick={() =>
                    onToggleGroup(
                      group.items
                        .filter((i) => !isLocked(i.name))
                        .map((i) => i.name),
                      group.granted < group.total,
                    )
                  }
                >
                  {group.granted < group.total
                    ? t('page.access.check_all')
                    : t('page.access.uncheck_all')}
                </button>
              )}
            </span>
          </div>

          {/* Uprawnienie zaplanowane zostaje widoczne z powodem —
              ukrycie go zamienialoby przeoczenie w tajemnice. */}
          {group.state === 'planned' && (
            <div className="ge-note ge-note--warn">
              {t('page.access.planned')}: {group.note}
            </div>
          )}

          {group.items.map((item) => (
            <label className="ge-acc__item" key={item.name}>
              <input
                type="checkbox"
                disabled={isLocked(item.name)}
                checked={checked.has(item.name)}
                onChange={() => onToggle(item.name)}
              />
              <span>{t(`page.access.sub.${item.sub}`)}</span>
              <code className="ge-quiet">{item.name}</code>
              {item.origin && (
                <span className="ge-quiet ge-acc__origin">{item.origin}</span>
              )}
            </label>
          ))}
        </section>
      ))}
    </>
  );
}
