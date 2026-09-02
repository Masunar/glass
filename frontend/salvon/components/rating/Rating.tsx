import {
  FormHelperText,
  type FormHelperTextProps,
  FormLabel,
  type FormLabelProps,
  Rating as MUIRating,
  type RatingProps as MUIRatingProps,
} from '@mui/material';

import { type ReactNode, type SyntheticEvent } from 'react';

import { Div } from '@salvon/components/div';
import type { SlotItem } from '@salvon/types';

export type RatingProps = Omit<MUIRatingProps, 'slotProps' | 'onChange'> & {
  label?: ReactNode;
  error?: string;
  helperText?: string;
  slotProps?: {
    rating?: SlotItem<Pick<MUIRatingProps, 'slotProps'>>;
    formLabel?: SlotItem<
      Omit<FormLabelProps, 'children' | 'error' | 'required'>
    >;
    formHelper?: SlotItem<
      Omit<FormHelperTextProps, 'children' | 'error' | 'required'>
    >;
  };
  required?: boolean;
  onChange?: (value: number | null, event: SyntheticEvent) => void;
};
export default function Rating({
  color,
  label,
  error,
  helperText,
  slotProps,
  onChange,
  value,
  required = false,
  ...props
}: RatingProps) {
  const { rating, formHelper, formLabel } = slotProps ?? {};

  return (
    <Div>
      <FormLabel {...formLabel} error={!!error} required={!!label && required}>
        {label}
      </FormLabel>
      <div>
        <MUIRating
          {...props}
          value={value ?? 0}
          onChange={(e, v) => {
            if (onChange) {
              onChange(v, e);
            }
          }}
          slotProps={rating?.slotProps}
        />
      </div>
      <FormHelperText
        sx={{ padding: 0, margin: 0, ...(formHelper?.sx ?? {}) }}
        {...formHelper}
        error={!!error}
      >
        {error || helperText}
      </FormHelperText>
    </Div>
  );
}
