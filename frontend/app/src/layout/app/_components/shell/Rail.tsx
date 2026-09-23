import { Tooltip } from '@mui/material';

import { useNavigate } from 'react-router';

import { useTranslation } from '@salvon/hooks/useTranslation';

import LogoMark from '@app/components/layout/LogoMark';
import {
  type AppModule,
  HOME,
  appModules,
  entryPath,
  hasScreens,
  moduleAccessPermission,
} from '@app/config/modules';
import { useHasPermission } from '@app/hook/use-permissions';

type Props = {
  active: AppModule;
  /** Pulpit — listwa podświetla wtedy logo, nie kafelek modułu. */
  isHome: boolean;
  initials: string;
  onUserClick: () => void;
};

/**
 * Listwa modułów — jedyne ciemne miejsce w jasnym motywie.
 *
 * Kolor kafelka niesie znaczenie, nie dekorację: ten sam odcień wraca
 * potem w pasmach, kropkach etapów i paskach decyzyjnych danego modułu.
 */
export default function Rail({ active, isHome, initials, onUserClick }: Props) {
  const t = useTranslation();
  const navigate = useNavigate();
  const hasPermissionTo = useHasPermission();

  return (
    <nav className="ge-rail" aria-label={t('page.module.nav')}>
      {/* Logo jest jedyną drogą powrotną na pulpit. Dopóki było
          `<span>`, ekran istniał i nie dawał się otworzyć — czyli był
          wart tyle co ekran, którego nie ma. */}
      <Tooltip title={t('page.home.title')} placement="right">
        <button
          type="button"
          className={
            isHome
              ? 'ge-rail__brand ge-rail__brand--link is-active'
              : 'ge-rail__brand ge-rail__brand--link'
          }
          aria-current={isHome ? 'page' : undefined}
          onClick={() => void navigate(HOME)}
        >
          <LogoMark size={20} title={t('brand_name')} />
        </button>
      </Tooltip>

      {appModules.map((module) => {
        const screens = hasScreens(module);
        const allowed = hasPermissionTo(moduleAccessPermission(module.key));
        const available = screens && allowed;
        const isActive = !isHome && module.key === active.key;
        const target = entryPath(module);

        return (
          <Tooltip
            key={module.key}
            title={
              t(module.labelKey) +
              // Wyglad kafelka jest jeden, ale powod dwa. Dymek mowiacy
              // „pusty modul" o module, ktory ekrany ma, kazalby szukac
              // bledu tam, gdzie go nie ma.
              (available
                ? ''
                : ` — ${t(screens ? 'page.module.denied' : 'page.module.empty')}`)
            }
            placement="right"
          >
            <button
              type="button"
              className={[
                'ge-rail__mod',
                isActive ? 'is-active' : '',
                available ? '' : 'is-empty',
              ]
                .filter(Boolean)
                .join(' ')}
              style={{ ['--ge-mod' as string]: `var(--m-${module.key})` }}
              aria-current={isActive ? 'page' : undefined}
              aria-disabled={available ? undefined : true}
              onClick={() => {
                if (available && target) {
                  void navigate(target);
                }
              }}
            >
              {module.code}
            </button>
          </Tooltip>
        );
      })}

      <button
        type="button"
        className="ge-rail__user"
        aria-label={t('page.module.account')}
        onClick={onUserClick}
      >
        {initials}
      </button>
    </nav>
  );
}
