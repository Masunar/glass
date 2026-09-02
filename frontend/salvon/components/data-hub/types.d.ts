import { type TextFieldProps, type TypographyProps } from '@mui/material';

import type { ReactNode } from 'react';

import type { CardProps } from '@salvon/components/card';
import type { DivProps, FlexProps } from '@salvon/components/div';
import type { IconButtonProps } from '@salvon/components/icon-button';
import type { LinkProps } from '@salvon/components/navigation';
import type { SlotItem } from '@salvon/types';
import type { GeneratePathUrl } from '@salvon/utils/generate-path';

export type DataHubItem = {
  label: string;
  path?: GeneratePathUrl;
  onClick?: () => void;
  icon?: ReactNode;
  keywords?: string;
  disabled?: boolean;
};

export type DataHubCategoryIcon = {
  element?: ReactNode;
  color?: string;
  bgColor?: string;
};

export type DataHubCategory = {
  id: string;
  label: string;
  path?: GeneratePathUrl;
  onClick?: () => void;
  accentColor?: string;
  bgColor?: string;
  icon?: DataHubCategoryIcon | null;
  items: DataHubItem[];
};

export type DataHubLabels = {
  count?: (count: number) => string;
  summary?: (categories: number, items: number) => ReactNode;
  searchPlaceholder?: string;
  searchInCategory?: string;
  noResults?: string;
  back?: string;
};

export type DataHubColumns = {
  xs?: string;
  sm?: string;
  md?: string;
  lg?: string;
};

export type DataHubSlotProps = {
  root?: SlotItem<FlexProps>;
  header?: SlotItem<FlexProps>;
  headingTitle?: SlotItem<TypographyProps>;
  summary?: SlotItem<TypographyProps>;
  search?: SlotItem<TextFieldProps>;
  grid?: SlotItem<DivProps>;
  card?: SlotItem<CardProps>;
  title?: SlotItem<TypographyProps>;
  sample?: SlotItem<TypographyProps>;
  row?: SlotItem<CardProps>;
  rowLink?: SlotItem<LinkProps>;
  rowLabel?: SlotItem<TypographyProps>;
  backButton?: SlotItem<IconButtonProps>;
  iconBadge?: { size?: number; sx?: FlexProps['sx'] };
};

export type DataHubProps = {
  categories: DataHubCategory[];
  heading?: {
    icon?: ReactNode;
    title?: ReactNode;
  };
  columns?: DataHubColumns;
  searchable?: boolean;
  sampleCount?: number;
  searchIcon?: ReactNode | null;
  rowChevron?: ReactNode | null;
  selectedId?: string | null;
  onSelect?: (id: string | null) => void;
  onSearch?: (query: string) => void;
  filter?: (item: DataHubItem, query: string) => boolean;
  labels?: DataHubLabels;

  renderHeader?: (ctx: { categories: number; items: number }) => ReactNode;
  renderCard?: (category: DataHubCategory, open: () => void) => ReactNode;
  renderRow?: (item: DataHubItem) => ReactNode;
  renderDetail?: (category: DataHubCategory, back: () => void) => ReactNode;

  slotProps?: DataHubSlotProps;
};
