import {
  FormControl,
  type FormControlProps,
  InputLabel,
  Select as MUISelect,
  type SelectProps as MUISelectProps,
  MenuItem,
  type MenuItemProps,
  type SelectChangeEvent,
} from '@mui/material';

import type { ReactNode } from 'react';

import type { SlotItem } from '@salvon/types';

export type SimpleSelectProps<T extends string | number> = {
  value?: T | null;
  label?: string | number;
  size?: 'small' | 'medium';
  onChange?: (event: SelectChangeEvent<any>, child: ReactNode) => void;
  fullWidth?: boolean;
  options?: Array<{
    value: T | null;
    label: string | number;
    props?: MenuItemProps;
  }>;
  slotProps?: {
    formControl?: SlotItem<FormControlProps>;
    select?: SlotItem<MUISelectProps>;
  };
};

export default function SimpleSelect<T extends string | number>({
  value,
  onChange,
  size,
  label,
  slotProps,
  options = [],
  fullWidth = true,
}: SimpleSelectProps<T>) {
  const { formControl, select } = slotProps ?? {};

  return (
    <FormControl {...formControl} fullWidth={fullWidth} size={size}>
      {label && <InputLabel>{label}</InputLabel>}
      <MUISelect
        {...select}
        value={value}
        label={label}
        onChange={onChange}
        fullWidth={fullWidth}
        size={size}
      >
        {options.map((option) => (
          <MenuItem
            key={option.value ?? String.random(4)}
            {...option.props}
            value={option.value ?? ''}
          >
            {option.label}
          </MenuItem>
        ))}
      </MUISelect>
    </FormControl>
  );
}
