import { type CSSProperties, type ForwardedRef, type ReactNode } from 'react';

import { type DivProps, type FlexProps } from '@salvon/components/div';
import { type SlotItem } from '@salvon/types';

export type SchedulerView =
  'year' | 'month' | 'week' | 'day' | 'list' | 'day_list';

export type SchedulerVariant = 'calendar' | 'split' | 'compact';

export type SchedulerRange = { start: Date; end: Date };

export type SchedulerCalendar = {
  id: string;
  label: string;
  color: string;
  hidden?: boolean;
};

export type SchedulerAccessors<T> = {
  getId: (event: T) => string;
  getTitle: (event: T) => ReactNode;
  getStart: (event: T) => Date;
  getEnd: (event: T) => Date;
  getAllDay?: (event: T) => boolean;
  getCalendarId?: (event: T) => string;
  getColor?: (event: T) => string | undefined;
};

export type SchedulerEventChange<T> = {
  event: T;
  start: Date;
  end: Date;
  allDay: boolean;
  type: 'drop' | 'resize';
};

export type SchedulerSlotSelection = {
  start: Date;
  end: Date;
  allDay: boolean;
};

export type SchedulerSlotProps = {
  root?: SlotItem<DivProps>;
  header?: SlotItem<FlexProps>;
  toolbar?: SlotItem<FlexProps>;
  calendar?: SlotItem<DivProps>;
  legend?: SlotItem<FlexProps>;
};

export type SchedulerRef = ForwardedRef<{
  navigate: (date: Date) => void;
  setView: (view: SchedulerView) => void;
}>;

export type SchedulerBaseProps<T> = SchedulerAccessors<T> & {
  events: T[];

  variant?: SchedulerVariant;

  title?: ReactNode;
  subtitle?: ReactNode;
  icon?: ReactNode;

  calendars?: SchedulerCalendar[];

  legend?: boolean;
  hiddenCalendars?: string[];
  defaultHiddenCalendars?: string[];
  onHiddenCalendarsChange?: (hidden: string[]) => void;

  views?: SchedulerView[];
  viewLabels?: Partial<Record<SchedulerView, ReactNode>>;

  view?: SchedulerView;
  defaultView?: SchedulerView;
  onViewChange?: (view: SchedulerView) => void;

  date?: Date;
  defaultDate?: Date;
  onNavigate?: (date: Date, view: SchedulerView) => void;

  onRangeChange?: (range: SchedulerRange, view: SchedulerView) => void;

  selectable?: boolean;
  onSelectSlot?: (slot: SchedulerSlotSelection) => void;
  onSelectEvent?: (event: T) => void;

  editable?: boolean;
  onEventChange?: (change: SchedulerEventChange<T>) => void;

  renderEvent?: (event: T) => ReactNode;

  loading?: boolean;
  height?: CSSProperties['height'];

  slotProps?: SchedulerSlotProps;
  ref?: SchedulerRef;
};

export type SchedulerProps<T> = SchedulerBaseProps<T>;

export type SchedulerFetcher<T> = (range: SchedulerRange) => Promise<T[]>;

export type ApiSchedulerProps<T> = Omit<SchedulerBaseProps<T>, 'events'> & {
  fetcher: SchedulerFetcher<T>;
  reloadKey?: string | number;
};
