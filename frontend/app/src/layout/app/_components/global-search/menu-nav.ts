import type { MenuEntry } from '@salvon/components/sidebar-menu/types';

export type FlatEntry = {
  path: string;
  /** Own translation key. */
  translation: string;
  /** Ancestor translation keys, outermost first, for a breadcrumb label. */
  trail: string[];
  icon?: any;
};

export type NavGroup = {
  label: string;
  entries: MenuEntry[];
};

/**
 * Flatten a menu subtree into navigable leaf entries. Headers/dividers only
 * delimit top-level groups (see buildNavGroups) and are skipped here. Nested
 * entries keep their ancestor chain in `trail` so they render as a breadcrumb.
 */
export function flattenMenu(
  entries: MenuEntry[],
  canAccess: (entry: MenuEntry) => boolean,
  parentIcon?: any,
  trail: string[] = [],
): FlatEntry[] {
  const out: FlatEntry[] = [];

  for (const entry of entries) {
    if (entry.type === 'header' || entry.type === 'divider') {
      continue;
    }
    if (!canAccess(entry)) {
      continue;
    }

    const icon = entry.icon ?? parentIcon;
    const path =
      typeof entry.route === 'string' ? entry.route : entry.route?.path;

    if (path && entry.translation) {
      out.push({ path, translation: entry.translation, trail, icon });
    }
    if (entry.children?.length) {
      const childTrail = entry.translation
        ? [...trail, entry.translation]
        : trail;
      out.push(...flattenMenu(entry.children, canAccess, icon, childTrail));
    }
  }

  return out;
}

/** Split the top-level menu into groups delimited by `header` entries. */
export function buildNavGroups(entries: MenuEntry[]): NavGroup[] {
  const groups: NavGroup[] = [{ label: 'pages', entries: [] }];

  for (const entry of entries) {
    if (entry.type === 'header') {
      const label =
        entry.translation ??
        (typeof entry.label === 'string' ? entry.label : undefined) ??
        'pages';
      groups.push({ label, entries: [] });
      continue;
    }
    if (entry.type === 'divider') {
      continue;
    }
    groups[groups.length - 1].entries.push(entry);
  }

  return groups;
}
