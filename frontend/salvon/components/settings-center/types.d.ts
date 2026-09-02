import { type TextFieldProps, type TypographyProps } from '@mui/material';

import type { ComponentType, ReactNode } from 'react';

import type { SettingsHeaderProps } from './SettingsHeader';

import type { DivProps, FlexProps } from '@salvon/components/div';
import type { OverlayLoadingProps } from '@salvon/components/progress';
import type { SlotItem } from '@salvon/types';

export type SettingsCenterItem = {
  id: string;
  label: string;
  description?: string;
  icon?: ReactNode;
  group?: string;
  keywords?: string;
  disabled?: boolean;
  /** Optional right-aligned badge in the sidebar, e.g. "4/7". */
  count?: ReactNode;
  /**
   * Whole panel renderer. `ctx.onBack` is only set on mobile (where the panes
   * swap) — render a back affordance in place of the panel's leading icon.
   */
  render?: (item: SettingsCenterItem, ctx: SettingsCenterRenderCtx) => ReactNode;
};

export type SettingsCenterRenderCtx = {
  /** Mobile-only back handler (panes swap on mobile). */
  onBack?: () => void;
  /**
   * Fixed-height panel header wrapper. Put your title/actions inside it and
   * it stays aligned with the sidebar search band automatically — `onBack` is
   * already wired in. Prefer this over hand-matching header paddings.
   */
  Header: ComponentType<SettingsHeaderProps>;
};

export type SettingsCenterLayout = {
  /** Header band height (search / panel title row). Default 74. */
  headerHeight?: number;
  /** Content-pane horizontal padding, theme spacing units. Default 3. */
  contentPx?: number;
  /** Content-pane vertical padding, theme spacing units. Default 2. */
  contentPy?: number;
  /** Sidebar padding, theme spacing units. Default 1.5. */
  sidebarPad?: number;
};

export type SettingsCenterLabels = {
  searchPlaceholder?: string;
  noResults?: string;
  emptyState?: ReactNode;
  /** Mobile "back to list" button label. */
  back?: string;
};

export type SettingsCenterSlotProps = {
  root?: SlotItem<FlexProps>;
  sidebar?: SlotItem<FlexProps>;
  search?: SlotItem<TextFieldProps>;
  list?: SlotItem<DivProps>;
  groupLabel?: SlotItem<TypographyProps>;
  content?: SlotItem<DivProps>;
  itemLabel?: SlotItem<TypographyProps>;
};

export type SettingsCenterProps = {
  items: SettingsCenterItem[];
  searchable?: boolean;
  searchIcon?: ReactNode | null;
  /** Sidebar column width. */
  sidebarWidth?: number | string;
  /** Fixed height so panes scroll independently; omit to grow with content. */
  height?: number | string;

  /** Show a blocking loading overlay over the whole component. */
  loading?: boolean;
  /** Extra props for the loading overlay (tip, loaderProps, overlaySx…). */
  loadingProps?: Omit<OverlayLoadingProps, 'loading' | 'children'>;

  /**
   * Shared layout dimensions that keep the sidebar band and the panel header
   * aligned. These are contract values (read in several places at once), not
   * per-slot styling — use `slotProps` for looks.
   */
  layout?: SettingsCenterLayout;

  selectedId?: string | null;
  defaultSelectedId?: string | null;
  onSelect?: (id: string | null) => void;
  onSearch?: (query: string) => void;
  filter?: (item: SettingsCenterItem, query: string) => boolean;

  labels?: SettingsCenterLabels;

  /** Custom sidebar entry. Falls back to the built-in icon/label/count row. */
  renderItem?: (
    item: SettingsCenterItem,
    ctx: { active: boolean; select: () => void },
  ) => ReactNode;
  /** Panel renderer used when an item has no own `render`. */
  renderContent?: (
    item: SettingsCenterItem,
    ctx: SettingsCenterRenderCtx,
  ) => ReactNode;

  slotProps?: SettingsCenterSlotProps;
};
