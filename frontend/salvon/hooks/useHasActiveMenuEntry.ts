import { useMemo } from 'react';
import { useLocation } from 'react-router';

import type { MenuEntry } from '@salvon/components/sidebar-menu';
import { findCurrentMenuEntry } from '@salvon/utils/menu';

export const useHasActiveMenuEntry = (
  menuEntries: Array<MenuEntry>,
): boolean => {
  const location = useLocation();

  return useMemo(() => {
    return !!findCurrentMenuEntry(menuEntries, location.pathname);
  }, [menuEntries, location.pathname]);
};
