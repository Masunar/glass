import type { BoxProps } from '@mui/material';

import type { FC, ReactNode } from 'react';

export type OmniSearchItemType = {
  key: string;
  search_name: string;
  /**
   * Preferred item shape: OmniSearch renders the row (icon chip, label,
   * shortcut, trailing arrow / enter badge) for you.
   */
  icon?: FC | ReactNode;
  label?: ReactNode;
  /**
   * Keyboard hint shown on the right, e.g. "G → K". Split on "→" into keys.
   */
  shortcut?: string;
  /** Hide the trailing ↗ arrow (shown by default for built-in rows). */
  hideArrow?: boolean;
  /**
   * Escape hatch: fully custom row content. When set it overrides the built-in
   * layout. Kept for backwards compatibility.
   */
  render?: FC;
  /**
   * Invoked when the item is chosen (click or Enter on the highlighted row).
   * Preferred over putting an onClick inside `render`, so keyboard selection
   * works. Return false to keep the dialog open.
   */
  onSelect?: () => void | boolean;
  boxProps?: Partial<BoxProps>;
};

export type OmniSearchGroupType = {
  key: string;
  label: ReactNode;
  items: OmniSearchItemType[];
};

export type OmniSearchFooterLabels = {
  navigation?: ReactNode;
  open?: ReactNode;
  close?: ReactNode;
  /** Rendered as `${count} ${results}`. */
  results?: ReactNode;
};
