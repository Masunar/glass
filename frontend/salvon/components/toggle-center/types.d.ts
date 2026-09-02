import { type SwitchProps as MUISwitchProps, type TypographyProps } from '@mui/material';

import type { ReactNode } from 'react';

import type { SwitchVariant } from '@salvon/components/switch';
import type {
  SettingsCenterProps,
  SettingsCenterSlotProps,
} from '@salvon/components/settings-center';

/** A single toggleable option inside a group. */
export type ToggleOption = {
  id: string;
  label: string;
  description?: string;
  icon?: ReactNode;
  disabled?: boolean;
};

/** A group of toggles = one sidebar entry + one right-hand panel. */
export type ToggleGroup = {
  id: string;
  label: string;
  description?: string;
  icon?: ReactNode;
  /** Sidebar grouping header (e.g. "KATALOG", "SPRZEDAŻ"). */
  section?: string;
  keywords?: string;
  disabled?: boolean;
  options: ToggleOption[];
  /** Per-section footer; overrides ToggleCenter's `footer` for this group. */
  footer?: ReactNode;
};

/** Map of optionId -> enabled, per group id. */
export type ToggleCenterValue = Record<string, Record<string, boolean>>;

/** Payload handed to `onToggle`: what changed + controls to flip related options. */
export type ToggleCenterItemOnToggle = {
  groupId: string;
  optionId: string;
  /** New state of the toggled option. */
  enabled: boolean;
  /** Set one option in any group (commits immediately). */
  setOption: (groupId: string, optionId: string, enabled: boolean) => void;
  /** Set many options at once in a single commit. Sparse patch is merged. */
  setOptions: (patch: ToggleCenterValue) => void;
  /** Full matrix as of just after the user's change. */
  allValues: ToggleCenterValue;
};

export type ToggleCenterLabels = {
  searchPlaceholder?: string;
  noResults?: string;
  selectAll?: string;
  clear?: string;
  /** e.g. (granted, total) => `${granted} z ${total} uprawnień nadanych` */
  granted?: (granted: number, total: number) => ReactNode;
};

export type ToggleCenterSlotProps = SettingsCenterSlotProps & {
  panelTitle?: SettingsCenterSlotProps['itemLabel'];
  rowLabel?: TypographyProps;
  rowDescription?: TypographyProps;
};

export type ToggleCenterProps = {
  groups: ToggleGroup[];
  value?: ToggleCenterValue;
  defaultValue?: ToggleCenterValue;
  onChange?: (
    /** Full t/f matrix for every group/option. */
    all: ToggleCenterValue,
    /** Only the enabled (true) entries; groups with none are omitted. */
    selected: ToggleCenterValue,
    /** What changed. `optionId` is unset for select-all/clear. */
    changed: { groupId: string; optionId?: string },
  ) => void;

  /**
   * Fires on a single option toggle (not select-all/clear). Use it to warn the
   * user (e.g. notify "enable X too") and to flip related options via helpers.
   */
  onToggle?: (toggle: ToggleCenterItemOnToggle) => void;

  switchVariant?: SwitchVariant;
  switchProps?: MUISwitchProps;

  /** Default footer for every panel; a group's own `footer` overrides it. */
  footer?: ReactNode;

  labels?: ToggleCenterLabels;
  slotProps?: ToggleCenterSlotProps;
} & Pick<
  SettingsCenterProps,
  | 'searchable'
  | 'searchIcon'
  | 'sidebarWidth'
  | 'height'
  | 'loading'
  | 'loadingProps'
  | 'layout'
  | 'selectedId'
  | 'defaultSelectedId'
  | 'onSelect'
>;
