import type { SxProps } from '@mui/material';

import type { FunctionComponent, ReactNode } from 'react';

import type {
  HasPermissionToCallback,
  MenuEntry,
} from '@salvon/components/sidebar-menu';

export type MenuDropdown = {
  compactMode: boolean;
  isNested?: boolean;
  items: Array<MenuEntry>;
  title: ReactNode;
  icon?: string | FunctionComponent;
  sx?: SxProps;
  permissions?: Array<string>;
  regex?: string | RegExp;
  hasPermissionTo?: HasPermissionToCallback;
};

export type PopoverDropdown = MenuDropdown & {
  firstOnClick?: boolean;
  enableHover?: boolean;
};
