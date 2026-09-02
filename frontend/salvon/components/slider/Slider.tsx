import {
  FormHelperText,
  type FormHelperTextProps,
  FormLabel,
  type FormLabelProps,
  type SliderProps as MUISliderProps,
} from '@mui/material';

import type { ReactNode } from 'react';

import { Div } from '@salvon/components/div';
import StyledSlider from '@salvon/components/slider/StyledSlider';
import type { SlotItem } from '@salvon/types';
import { isArray } from '@salvon/utils/type-check';

export type SliderProps = Omit<MUISliderProps, 'slotProps' | 'onChange'> & {
  label?: ReactNode;
  error?: string;
  helperText?: string;
  onChange?: (
    value: number | number[],
    event: Event,
    activeThumb: number,
  ) => void;
  slotProps?: {
    slider?: SlotItem<Pick<MUISliderProps, 'slotProps'>>;
    formLabel?: SlotItem<Omit<FormLabelProps, 'children' | 'error'>>;
    formHelper?: SlotItem<Omit<FormHelperTextProps, 'children' | 'error'>>;
  };
  range?: boolean;
  required?: boolean;
};
export default function Slider({
  color,
  label,
  error,
  helperText,
  onChange,
  slotProps,
  value,
  required = false,
  range = false,
  size = 'small',
  step = 1,
  valueLabelDisplay = 'auto',
  ...props
}: SliderProps) {
  const { slider, formHelper, formLabel } = slotProps ?? {};

  if (range && !isArray(value ?? []) && !isArray(props?.defaultValue ?? [])) {
    throw new Error('Multiple range must be used with arrayable value.');
  }

  if (
    !range &&
    isArray(value ?? undefined) &&
    isArray(props?.defaultValue ?? undefined)
  ) {
    throw new Error('Arrayable values must be used with range prop.');
  }

  return (
    <Div>
      <FormLabel {...formLabel} error={!!error} required={!!label && required}>
        {label}
      </FormLabel>
      <StyledSlider
        {...props}
        value={value}
        valueLabelDisplay={valueLabelDisplay}
        slotProps={slider?.slotProps}
        step={step}
        size={size}
        color={error ? 'error' : color}
        onChange={(event, value, activeThumb) => {
          if (onChange) {
            onChange(value, event, activeThumb);
          }
        }}
      />
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
