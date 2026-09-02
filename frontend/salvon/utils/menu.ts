import { matchPath } from 'react-router';

import type { MenuEntry } from '@salvon/components/sidebar-menu';
import { isString } from '@salvon/utils/type-check';

export const findCurrentMenuEntry = (
  menuEntries: MenuEntry[],
  currentPath: string,
): MenuEntry | null => {
  for (const entry of menuEntries) {
    const path = !entry.route
      ? '#'
      : ((isString(entry.route) ? entry.route : entry.route.path) ?? '');

    if (path && matchPath(currentPath, path)) {
      return entry;
    }
    if (entryMatchesRegex(entry.regex, currentPath)) {
      return entry;
    }

    if (entry.children) {
      const childrenActiveRoute = findCurrentMenuEntry(
        entry.children,
        currentPath,
      );
      if (childrenActiveRoute) {
        return childrenActiveRoute;
      }
    }
  }
  return null;
};

export const entryMatchesRegex = (
  regex: string | RegExp | undefined,
  url: string,
) => {
  if (!regex) {
    return false;
  }
  return new RegExp(regex).test(url);
};
