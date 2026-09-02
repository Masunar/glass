import type { TextFieldProps } from '@mui/material';

import type { Dayjs } from 'dayjs';
import type { ReactNode } from 'react';

import type { FlexProps } from '@salvon/components/div';
import type { PopoverProps } from '@salvon/components/popover';
import type { SlotItem } from '@salvon/types';

export type PickerCalendarMode = 'date' | 'datetime' | 'time';

/** Internal navigation view for the calendar part. */
export type CalendarView = 'day' | 'month' | 'year';

/** Time granularity columns. */
export type TimeView = 'hours' | 'minutes' | 'seconds';

/** Any view accepted by the public `views` prop (date-nav + time columns). */
export type PickerCalendarView = CalendarView | TimeView;

export type QuickAction = {
  label: ReactNode;
  /** Returns the value to apply; receives the current value (may be null). */
  value: (current: Dayjs | null) => Dayjs;
};

/** Helpers passed to a custom `renderFooter` (which fully replaces the footer). */
export type PickerCalendarFooterProps = {
  /** Current selected value. */
  value: Dayjs | null;
  /** Current value formatted (respects `format` / views / locale). */
  formattedValue: string;
  /**
   * Set the value directly — build any Dayjs yourself and pass it, e.g. a
   * "next month + 1 day" quick action: `setValue(dayjs().add(1,'month').add(1,'day'))`.
   */
  setValue: (value: Dayjs | null) => void;
  /**
   * Set a value (optional) and confirm in one call — for quick-action buttons
   * that should both pick and apply. Omit the arg to just confirm the current value.
   */
  apply: (value?: Dayjs | null) => void;
  /** Clear the value (fires the picker's onClear). */
  clear: () => void;
};

export type PickerCalendarLabels = {
  clear?: string;
  apply?: string;
  hour?: string;
  minute?: string;
  second?: string;
  time?: string;
  meridiem?: string;
};

export type PickerCalendarProps = {
  mode?: PickerCalendarMode;

  /** Full width — fill the parent instead of sizing to content. */
  fw?: boolean;

  value?: Dayjs | null;
  defaultValue?: Dayjs | null;
  onChange?: (value: Dayjs | null, formattedValue: string) => void;

  /** Fires when the user confirms with the apply button. */
  onApply?: (value: Dayjs | null) => void;
  /** Fires when the user clears. */
  onClear?: () => void;

  minDate?: Dayjs;
  maxDate?: Dayjs;

  /** Override the locale used for formatting. Defaults to the app's current locale. */
  locale?: string;

  /**
   * dayjs format string for the footer summary / `formattedValue`. Overrides
   * the auto-derived format (which follows the active views). e.g. `'DD.MM.YYYY HH:mm'`.
   */
  format?: string;

  /** 24-hour clock (default) or 12-hour clock with an AM/PM toggle. */
  hourCycle?: 12 | 24;

  /**
   * Which views are available. Covers both the calendar (`year`/`month`/`day`)
   * and the time columns (`hours`/`minutes`/`seconds`). The finest calendar
   * view present is where a date pick is committed — e.g. `['year']` for a
   * year-only picker, `['year','month']` for month+year. Time views select
   * which spinners show — e.g. `['minutes','seconds']` for a mm:ss picker.
   * Defaults are derived from `mode`.
   */
  views?: PickerCalendarView[];
  /** Which calendar view to open on first render. Defaults to the finest calendar view in `views`. */
  openTo?: CalendarView;

  /** Hide the footer (summary + clear/apply). */
  hideFooter?: boolean;
  /** Render leading/trailing days of adjacent months as empty cells. */
  hideOutsideDays?: boolean;
  /** Quick-action chips shown in the footer (date/datetime only). */
  quickActions?: QuickAction[];
  /**
   * Render a custom footer instead of the built-in one. Receives the current
   * value, a formatted summary, and the same helpers the default footer uses.
   */
  renderFooter?: (props: PickerCalendarFooterProps) => ReactNode;

  labels?: PickerCalendarLabels;

  slotProps?: {
    root?: SlotItem<FlexProps>;
  };
};

export type DateRange = {
  start: Dayjs | null;
  end: Dayjs | null;
};

export type RangePreset = {
  label: ReactNode;
  value: () => DateRange;
};

/** Helpers passed to a range picker's custom `renderFooter`. */
export type RangePickerCalendarFooterProps = {
  value: DateRange;
  /** Formatted endpoints. */
  formattedValue: { start: string; end: string };
  /** Number of days in the range (null until both ends are set). */
  dayCount: number | null;
  setValue: (value: DateRange) => void;
  apply: (value?: DateRange) => void;
  clear: () => void;
};

export type RangePickerCalendarLabels = {
  clear?: string;
  apply?: string;
  /** Unit shown after the day count, e.g. "dni". */
  days?: string;
  /** Label before the start time input, e.g. "OD". */
  from?: string;
  /** Label before the end time input, e.g. "DO". */
  to?: string;
};

export type RangePickerCalendarProps = {
  /** Full width — fill the parent instead of sizing to content. */
  fw?: boolean;

  value?: DateRange | null;
  defaultValue?: DateRange | null;
  onChange?: (
    value: DateRange,
    formatted: { start: string; end: string },
  ) => void;
  onApply?: (value: DateRange) => void;
  onClear?: () => void;

  /** Range preset chips shown above the footer. */
  presets?: RangePreset[];

  /** Show a single month panel instead of two side-by-side. */
  singleMonth?: boolean;

  minDate?: Dayjs;
  maxDate?: Dayjs;
  locale?: string;
  /** dayjs format for the *date* part of each endpoint's summary. */
  format?: string;

  /**
   * Enabled views — calendar (`year`/`month`/`day`) and/or time
   * (`hours`/`minutes`/`seconds`). Defaults to `['year','month','day']`.
   * Any time view shows an OD/DO time input row below the calendars and makes
   * each endpoint a datetime; the finest calendar view is where a pick commits.
   */
  views?: PickerCalendarView[];
  /** 24-hour clock (default) or 12-hour clock (time views only). */
  hourCycle?: 12 | 24;

  hideFooter?: boolean;
  /** Render leading/trailing days of adjacent months as empty cells. */
  hideOutsideDays?: boolean;
  /** Render a custom footer instead of the built-in one. Not called when the range is empty. */
  renderFooter?: (props: RangePickerCalendarFooterProps) => ReactNode;
  labels?: RangePickerCalendarLabels;

  slotProps?: {
    root?: SlotItem<FlexProps>;
  };
};

type PickerInputSlotProps = {
  textField?: SlotItem<TextFieldProps>;
  popover?: SlotItem<
    Omit<
      PopoverProps,
      'open' | 'setOpen' | 'anchor' | 'anchorEl' | 'onClose' | 'ref'
    >
  >;
};

/**
 * A `PickerCalendar` wrapped in an MUI text input that opens the calendar in a
 * popover. Commits on the calendar's apply button; clearing empties the input.
 */
export type PickerInputProps = Omit<PickerCalendarProps, 'fw' | 'slotProps'> & {
  /** Full width — the text input fills its parent. */
  fw?: boolean;
  label?: string;
  required?: boolean;
  error?: boolean;
  helperText?: ReactNode;
  disabled?: boolean;
  /** Value display format; also shown as the input placeholder/mask hint. */
  displayFormat?: string;
  /** Show a clear (×) button when a value is set. Defaults to `true`. */
  clearable?: boolean;
  onBlur?: TextFieldProps['onBlur'];
  slotProps?: PickerInputSlotProps;
};

/**
 * A `RangePickerCalendar` wrapped in an MUI text input that opens the range
 * calendar in a popover. Commits on the calendar's apply button.
 */
export type RangePickerInputProps = Omit<
  RangePickerCalendarProps,
  'fw' | 'slotProps'
> & {
  fw?: boolean;
  label?: string;
  required?: boolean;
  error?: boolean;
  helperText?: ReactNode;
  disabled?: boolean;
  /** Separator between start and end in the input. Defaults to `' - '`. */
  separator?: string;
  /**
   * Numeric format used for typing/parsing each endpoint in the input, also
   * shown as the placeholder mask. Kept separate from `format` (the popover
   * footer's display format). Defaults to `'DD.MM.YYYY'`.
   */
  displayFormat?: string;
  /** Show a clear (×) button on hover/focus when a value is set. Defaults to `true`. */
  clearable?: boolean;
  onBlur?: TextFieldProps['onBlur'];
  slotProps?: PickerInputSlotProps;
};
