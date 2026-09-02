import { Checkbox } from '@mui/material';

import type { FormCheckboxProps } from './types.d';
import { Controller, useFormContext } from 'react-hook-form';

import { CheckboxLabel } from '@salvon/components/label';

export default function FormCheckbox({
  name,
  label,
  helperText,
  rules,
  onChange,
  onBlur,
  slotProps,
  disabled,
  required,
  defaultValue,
  onChangeOverride,
  onBlurOverride,
}: FormCheckboxProps) {
  const { control } = useFormContext();

  return (
    <Controller
      name={name}
      control={control}
      rules={rules}
      defaultValue={defaultValue}
      disabled={disabled}
      render={({ field, fieldState: { error } }) => {
        const controlElement = (
          <Checkbox
            {...slotProps?.control}
            required={required}
            disabled={disabled || field.disabled}
            checked={field.value || false}
            onBlur={(event) => {
              if (onBlurOverride) {
                onBlurOverride(event, field.onBlur);
                return;
              }

              field.onBlur();

              if (onBlur) {
                onBlur(event);
              }
            }}
            onChange={(event, checked) => {
              if (onChangeOverride) {
                onChangeOverride(event, checked, field.onChange);
                return;
              }

              field.onChange(
                onChange ? (onChange(checked, event) ?? event) : event,
              );
            }}
          />
        );

        return (
          <>
            <CheckboxLabel
              error={error?.message}
              label={label}
              helperText={helperText}
              wrapper={slotProps?.wrapper}
              formControlLabel={slotProps?.formControlLabel}
              formHelperText={slotProps?.formHelperText}
            >
              {controlElement}
            </CheckboxLabel>
          </>
        );
      }}
    />
  );
}
