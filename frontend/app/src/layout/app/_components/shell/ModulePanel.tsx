import { NavLink } from 'react-router';

import { useTranslation } from '@salvon/hooks/useTranslation';

import type { AppModule } from '@app/config/modules';
import { useHasPermission } from '@app/hook/use-permissions';

type Props = {
  module: AppModule;
  meta?: string;
  children?: React.ReactNode;
  footer?: React.ReactNode;
};

/**
 * Panel modułu — filtry, ekrany modułu, zapisane widoki.
 *
 * Zawartość zmienia się z kontekstem: na liście trzyma filtry
 * z licznikami, w szczegółach zlecenia staje się nawigacją zlecenia.
 * Dlatego przyjmuje `children` zamiast mieć zaszytą strukturę.
 */
export default function ModulePanel({ module, meta, children, footer }: Props) {
  const t = useTranslation();
  const hasPermissionTo = useHasPermission();

  const links = module.links.filter(
    (link) =>
      link.path !== undefined &&
      (link.permission === undefined || hasPermissionTo([link.permission])),
  );

  return (
    <nav
      className="ge-panel"
      aria-label={t(module.labelKey)}
      style={{
        ['--ge-mod' as string]: `var(--m-${module.key})`,
        ['--ge-mod-tint' as string]: `var(--m-${module.key}-tint)`,
      }}
    >
      <div className="ge-panel__head">
        <div className="ge-panel__kicker">{t('page.module.kicker')}</div>
        <h2 className="ge-panel__title">{t(module.labelKey)}</h2>
        {meta && <div className="ge-panel__meta">{meta}</div>}
      </div>

      {children}

      {links.length > 0 && (
        <>
          <div className="ge-panel__group">{t('page.module.in_module')}</div>
          {links.map((link) => (
            <NavLink
              key={link.labelKey}
              to={link.path ?? '/'}
              className={({ isActive }) =>
                isActive ? 'ge-panel__link is-active' : 'ge-panel__link'
              }
            >
              {t(link.labelKey)}
            </NavLink>
          ))}
        </>
      )}

      {footer && <div className="ge-panel__foot">{footer}</div>}
    </nav>
  );
}
