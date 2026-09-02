import {
  FormControlLabel,
  type FormControlLabelProps,
  FormHelperText,
  type FormHelperTextProps,
} from '@mui/material';

import type { ReactElement, ReactNode } from 'react';

import { Div } from '@salvon/components/div';
import type { SlotItem } from '@salvon/types';

export type CheckboxLabelProps = {
  label?: ReactNode;
  helperText?: ReactNode;
  children: ReactElement;
  wrapper?: Omit<SlotItem<FormHelperTextProps>, 'error'>;
  formHelperText?: Omit<SlotItem<FormHelperTextProps>, 'error'>;
  formControlLabel?: SlotItem<FormControlLabelProps>;
  error?: string;
  forceError?: boolean;
};

export default function CheckboxLabel({
  label,
  helperText,
  wrapper,
  formHelperText,
  formControlLabel,
  error,
  children,
  forceError,
}: CheckboxLabelProps) {
  return (
    <FormHelperText
      component={Div}
      {...(wrapper ?? {})}
      error={!!error || forceError}
    >
      <FormControlLabel
        {...(formControlLabel ?? {})}
        control={children}
        label={label}
      />

      {(!!error || helperText) && (
        <FormHelperText
          {...(formHelperText ?? {})}
          error={!!error || forceError}
        >
          {error ?? helperText}
        </FormHelperText>
      )}
    </FormHelperText>
  );
}
