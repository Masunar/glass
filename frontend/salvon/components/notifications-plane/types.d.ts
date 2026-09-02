import type { BadgeProps, PaperProps } from '@mui/material';

import type { Dayjs } from 'dayjs';
import type { MouseEvent, ReactNode } from 'react';

import type { DivProps, FlexProps } from '@salvon/components/div';
import type { PopoverProps } from '@salvon/components/popover';
import type { SlotItem } from '@salvon/types';
import type { GeneratePathUrl } from '@salvon/utils/generate-path';

export type NotificationColor = {
  bg?: string;
  fg?: string;
};

export type NotificationAction = {
  label: ReactNode;
  onClick: () => void;
  disabled?: boolean;
};

export type NotificationItem = {
  id: string;
  title: ReactNode;
  description?: ReactNode;
  date?: string | number | Date | Dayjs;
  icon?: ReactNode;
  color?: NotificationColor;
  read?: boolean;
  path?: GeneratePathUrl;
  onOpen?: () => void;
  gotoLabel?: ReactNode;
  actions?: NotificationAction[];
};

export type NotificationTriggerProps = {
  onClick: (event: MouseEvent<HTMLElement>) => void;
  count: number;
  hasUnread: boolean;
  loading: boolean;
};

export type NotificationItemHelpers = {
  markRead: () => void;
  close: () => void;
  marking: boolean;
};

export type NotificationsPlaneLabel = {
  title?: ReactNode;
  loading?: ReactNode;
  newSection?: string;
  readSection?: string;
  noNew?: ReactNode;
  markAllRead?: ReactNode;
  markedRead?: ReactNode;
  showMore?: ReactNode;
  goto?: ReactNode;
  markReadHint?: string;
  summary?: (count: number) => ReactNode;
  today?: string;
  yesterday?: string;
};

export type NotificationsPlaneSlotProps = {
  badge?: SlotItem<BadgeProps>;
  popover?: SlotItem<PopoverProps>;
  paper?: SlotItem<PaperProps>;
  panel?: SlotItem<DivProps>;
  header?: SlotItem<DivProps>;
  list?: SlotItem<DivProps>;
  item?: SlotItem<FlexProps>;
};

export type NotificationsPlaneProps = {
  items: NotificationItem[];
  count?: number;
  readTotal?: number;
  loading?: boolean;
  hasMore?: boolean;
  loadingMore?: boolean;

  onOpen?: () => void;
  onRefresh?: () => void;
  onMarkAllRead?: () => void;
  onMarkRead?: (id: string) => void;
  onLoadMore?: () => void;
  onItemClick?: (item: NotificationItem) => void;

  markReadDelay?: number;
  defaultNewOpen?: boolean;
  defaultReadOpen?: boolean;
  width?: number;

  trigger?: (props: NotificationTriggerProps) => ReactNode;
  renderItem?: (
    item: NotificationItem,
    helpers: NotificationItemHelpers,
  ) => ReactNode;

  labels?: NotificationsPlaneLabel;
  slotProps?: NotificationsPlaneSlotProps;
  panelPlacement?: PopoverProps['placement'];
};
