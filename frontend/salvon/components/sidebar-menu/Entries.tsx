import { List, type ListProps } from '@mui/material';

import Entry from './Entry';
import type { HasPermissionToCallback, MenuEntry } from './types.d';
import type { ReactNode } from 'react';

import { Div } from '@salvon/components/div';

export type EntriesProps = ListProps & {
  items: Array<MenuEntry>;
  compactMode: boolean;
  isNested?: boolean;
  hasPermissionTo?: HasPermissionToCallback;
};

export default function Entries({
  items,
  compactMode,
  hasPermissionTo,
  isNested = false,
  ...props
}: EntriesProps) {
  return (
    <List {...props}>
      {items.map((entry: MenuEntry, index: number): ReactNode => (
        <Div
          key={index}
          sx={{
            marginBottom: index === items.length - 1 ? '0' : '5px',
          }}
        >
          <Entry
            hasPermissionTo={hasPermissionTo}
            compactMode={compactMode}
            entry={entry}
            isNested={isNested}
          />
        </Div>
      ))}
    </List>
  );
}
