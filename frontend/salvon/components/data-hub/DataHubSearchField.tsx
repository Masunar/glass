import type { TextFieldProps } from '@mui/material';

import type { ReactNode } from 'react';

import {
  SearchField,
  type SearchFieldProps,
} from '@salvon/components/_shared/search-field';
import { usePalette } from '@salvon/hooks/useTheme';
import type { SlotItem } from '@salvon/types';

export type DataHubSearchFieldProps = {
  value: string;
  onChange: (value: string) => void;
  placeholder?: string;
  autoFocus?: boolean;
  icon?: ReactNode | null;
  slotProps?: { field?: SlotItem<TextFieldProps> };
};

export default function DataHubSearchField(props: DataHubSearchFieldProps) {
  const palette = usePalette();
  return (
    <SearchField
      {...(props as SearchFieldProps)}
      token={palette.salvon?.data_hub?.search_field as object}
    />
  );
}
