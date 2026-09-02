import type { TabProps } from '@mui/material';

import type { ReactElement } from 'react';

import type { SlotItem } from '@salvon/types';

export type TabItem = {
  name: string;
  label: string;
  content: ReactElement;
  labelProps?: Omit<SlotItem<TabProps>, 'value' | 'label'>;
};

export type TabsItems = Array<TabItem>;
