import Entries from './Entries';
import type { HasPermissionToCallback, MenuEntry } from './types.d';
import { useMemo } from 'react';

export type MenuProps = {
  items: MenuEntry[];
  compactMode?: boolean;
  hasPermissionTo?: HasPermissionToCallback;
};

export default function Menu({
  items,
  hasPermissionTo,
  compactMode = false,
}: MenuProps) {
  return useMemo(
    () => (
      <Entries
        items={items}
        compactMode={compactMode}
        hasPermissionTo={hasPermissionTo}
      />
    ),
    [items, compactMode],
  );
}
