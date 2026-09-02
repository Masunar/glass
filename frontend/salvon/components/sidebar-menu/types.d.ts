import { type SxProps } from '@mui/material';

import type { FunctionComponent } from 'react';

import type { ApplicationRoute, RoutePermission } from '@salvon/router';

export type HasPermissionToCallback = (
  permission?: string | RoutePermission[],
  subPermission?: string,
) => boolean;

export type MenuEntry = {
  type?: 'item' | 'divider' | 'header' | 'group';
  label?: string | FunctionComponent;
  translation?: string;
  route?: ApplicationRoute | string;
  target?: '_self' | '_blank' | '_parent' | '_top';
  icon?: string | FunctionComponent;
  children?: Array<MenuEntry>;
  permissions?: Array<string> | string;
  sx?: SxProps;
  collapsedSx?: SxProps;
  regex?: RegExp | string;
};

export type MenuEntries = MenuEntry[];
