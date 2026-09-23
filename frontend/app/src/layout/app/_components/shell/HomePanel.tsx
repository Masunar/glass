import { NavLink } from 'react-router';

import { useTranslation } from '@salvon/hooks/useTranslation';

import {
  HOME,
  appModules,
  entryPath,
  moduleAccessPermission,
} from '@app/config/modules';
import { useHasPermission } from '@app/hook/use-permissions';

type Props = {
  children?: React.ReactNode;
  footer?: React.ReactNode;
};

/**
 * Menu główne — panel pulpitu.
 *
 * Pulpit **nie należy do żadnego modułu**, więc panel nie może być
 * panelem Zleceń z dopisaną pozycją: nagłówek mówiłby „MODUŁ Zlecenia"
 * nad ekranem, który do Zleceń nie należy. Zamiast ekranów modułu stoją
 * tu moduły — pulpit jest jedynym miejscem, z którego widać całą
 * aplikację naraz.
 */
export default function HomePanel({ children, footer }: Props) {
  const t = useTranslation();
  const hasPermissionTo = useHasPermission();

  const modules = appModules.filter(
    (module) =>
      entryPath(module) !== undefined &&
      hasPermissionTo(moduleAccessPermission(module.key)),
  );

  return (
    <nav
      className="ge-panel"
      aria-label={t('page.home.menu')}
      style={{
        // Stal jest akcentem systemu, nie kolorem Zlecen — patrz
        // `tokens.ts`. Pulpit jest ponad modulami, wiec bierze akcent.
        ['--ge-mod' as string]: 'var(--m-zlec)',
        ['--ge-mod-tint' as string]: 'var(--m-zlec-tint)',
      }}
    >
      <div className="ge-panel__head">
        <div className="ge-panel__kicker">{t('page.home.menu')}</div>
        <h2 className="ge-panel__title">{t('page.home.title')}</h2>
      </div>

      {children}

      <NavLink
        to={HOME}
        end
        className={({ isActive }) =>
          isActive ? 'ge-panel__link is-active' : 'ge-panel__link'
        }
      >
        {t('page.home.title')}
      </NavLink>

      {modules.length > 0 && (
        <>
          <div className="ge-panel__group">{t('page.home.modules')}</div>
          {modules.map((module) => (
            <NavLink
              key={module.key}
              to={entryPath(module) ?? HOME}
              className="ge-panel__link"
              style={{ ['--ge-mod' as string]: `var(--m-${module.key})` }}
            >
              {t(module.labelKey)}
            </NavLink>
          ))}
        </>
      )}

      {footer && <div className="ge-panel__foot">{footer}</div>}
    </nav>
  );
}
