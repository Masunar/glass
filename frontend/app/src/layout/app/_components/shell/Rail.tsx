import { Tooltip } from '@mui/material';

import { useNavigate } from 'react-router';

import { useTranslation } from '@salvon/hooks/useTranslation';

import LogoMark from '@app/components/layout/LogoMark';
import { type AppModule, appModules, hasScreens } from '@app/config/modules';

type Props = {
  active: AppModule;
  initials: string;
  onUserClick: () => void;
};

/**
 * Listwa modułów — jedyne ciemne miejsce w jasnym motywie.
 *
 * Kolor kafelka niesie znaczenie, nie dekorację: ten sam odcień wraca
 * potem w pasmach, kropkach etapów i paskach decyzyjnych danego modułu.
 */
export default function Rail({ active, initials, onUserClick }: Props) {
  const t = useTranslation();
  const navigate = useNavigate();

  return (
    <nav className="ge-rail" aria-label={t('page.module.nav')}>
      <span className="ge-rail__brand">
        <LogoMark size={20} title={t('brand_name')} />
      </span>

      {appModules.map((module) => {
        const available = hasScreens(module);
        const isActive = module.key === active.key;
        const target = module.links.find(
          (link) => link.path !== undefined,
        )?.path;

        return (
          <Tooltip
            key={module.key}
            title={
              t(module.labelKey) +
              (available ? '' : ` — ${t('page.module.empty')}`)
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
