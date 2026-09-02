import { buildNavGroups, flattenMenu } from './menu-nav';
import { useMemo, useState } from 'react';
import { IoSearchOutline } from 'react-icons/io5';
import { useNavigate } from 'react-router';

import { Div, Flex } from '@salvon/components/div';
import { BaseIconButton } from '@salvon/components/icon-button';
import { OmniSearch } from '@salvon/components/omni-search';
import type { OmniSearchGroupType } from '@salvon/components/omni-search';
import type { MenuEntry } from '@salvon/components/sidebar-menu/types';
import { usePalette } from '@salvon/hooks/useTheme';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { isMac } from '@salvon/utils/operating-system';

import menu from '@app/config/menu';
import { useHasPermission } from '@app/hook/use-permissions';

const shortcutLabel = isMac() ? '⌘ K' : 'Ctrl K';

export default function GlobalSearch() {
  const [open, setOpen] = useState(false);
  const t = useTranslation();
  const navigate = useNavigate();
  const hasPermissionTo = useHasPermission();
  const isDark = usePalette().mode === 'dark';
  const box = isDark
    ? {
        border: '#2c2c2c',
        bg: '#1a1a1a',
        hoverBorder: '#3a3a3a',
        hoverBg: '#1e1e1e',
      }
    : {
        border: '#e2e8f0',
        bg: '#f8fafc',
        hoverBorder: '#cbd5e1',
        hoverBg: '#fff',
      };

  const groups = useMemo<OmniSearchGroupType[]>(() => {
    const canAccess = (entry: MenuEntry) => {
      const route = typeof entry.route === 'object' ? entry.route : undefined;
      return hasPermissionTo(route?.permissions);
    };

    const navGroups: OmniSearchGroupType[] = buildNavGroups(menu)
      .map((g, i) => ({
        key: `nav:${g.label}`,
        // First nav group is the app pages ("Strony"); the CRM header that
        // delimits it in the sidebar is decorative here.
        label: t(i === 0 ? 'pages' : g.label),
        items: flattenMenu(g.entries, canAccess).map((entry) => {
          const crumbs = [...entry.trail, entry.translation].map((k) => t(k));
          return {
            key: `nav:${entry.path}`,
            search_name: crumbs.join(' '),
            label: crumbs.join(' › '),
            icon: entry.icon,
            onSelect: () => {
              navigate(entry.path);
            },
          };
        }),
      }))
      .filter((g) => g.items.length);

    // Przelaczanie motywu zdjete razem z trybem ciemnym - zostawienie
    // pozycji w wyszukiwarce dawaloby akcje bez skutku.
    return navGroups;
  }, [t, navigate, hasPermissionTo]);

  return (
    <>
      {/* Mobile: icon button matching the other topbar actions */}
      <Div sx={{ display: { xs: 'block', md: 'none' } }}>
        <BaseIconButton
          onClick={() => setOpen(true)}
          label={t('search_in_app')}
        >
          <IoSearchOutline />
        </BaseIconButton>
      </Div>

      {/* Desktop: full search box with placeholder and shortcut hint */}
      <Flex
        aCenter
        jBetween
        onClick={() => setOpen(true)}
        sx={{
          display: { xs: 'none', md: 'flex' },
          gap: 1,
          height: 40,
          px: 1.5,
          borderRadius: '10px',
          border: `1px solid ${box.border}`,
          background: box.bg,
          color: isDark ? '#6b7280' : '#94a3b8',
          cursor: 'pointer',
          transition: 'border-color 150ms, background 150ms',
          '&:hover': { borderColor: box.hoverBorder, background: box.hoverBg },
        }}
      >
        <Flex aCenter sx={{ gap: 1, minWidth: 0 }}>
          <IoSearchOutline size={18} />
          <Div sx={{ fontSize: '0.85rem', overflow: 'hidden' }}>
            {t('search_in_app')}
          </Div>
        </Flex>
        <Div
          sx={{
            flexShrink: 0,
            px: 0.75,
            py: 0.25,
            fontSize: '0.72rem',
            fontWeight: 600,
            letterSpacing: '0.02em',
            lineHeight: 1.4,
            color: 'inherit',
            border: `1px solid ${box.hoverBorder}`,
            borderRadius: '6px',
            background: isDark ? '#242424' : '#fff',
          }}
        >
          {shortcutLabel}
        </Div>
      </Flex>
      <OmniSearch
        open={open}
        setOpen={setOpen}
        searchPlaceholder={t('search_in_app')}
        noResultsItem={t('no_results')}
        groups={groups}
        footerLabels={{
          navigation: t('search_navigation'),
          open: t('search_open'),
          close: t('search_close'),
          results: t('search_results'),
        }}
      />
    </>
  );
}
