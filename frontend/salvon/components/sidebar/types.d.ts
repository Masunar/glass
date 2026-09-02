import type { DrawerProps, SxProps } from '@mui/material';

import type { ReactNode } from 'react';

import type { SlotItem } from '@salvon/types';

export type SidebarProps = {
  width: number;
  padding?: number;
  children: ReactNode;
  slotProps?: {
    drawer: SlotItem<DrawerProps>;
  };
  sx?: SxProps;
};
