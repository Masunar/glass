import { FormLabel } from '@mui/material';

import type { FormPickerInputProps } from './types.d';
import { Controller } from 'react-hook-form';

import { Flex } from '@salvon/components/div';
import { PickerInput } from '@salvon/components/picker-calendar';
import { useCurrentForm } from '@salvon/hooks/useForm';
import { usePalette } from '@salvon/hooks/useTheme';

export default function FormPickerInput({
  name,
  helperText,
  rules,
  onChange,
  onBlur,
  defaultValue = null,
  disabled,
  onChangeOverride,
  onBlurOverride,
  labelMode,
  label,
  required,
  ...props
}: FormPickerInputProps) {
  const { control } = useCurrentForm();
  const palette = usePalette();
  labelMode = labelMode ?? palette.salvon?.form?.labelMode ?? 'material';

  return (
    <Controller
      name={name}
      control={control}
      rules={rules}
      defaultValue={defaultValue}
      disabled={disabled}
      render={({ field, fieldState }) => {
        const { error } = fieldState;

        const formControl = (
          <PickerInput
            {...props}
            label={labelMode === 'above' ? undefined : label}
            disabled={disabled || field.disabled}
            required={required}
            error={!!error}
            helperText={error ? error?.message : helperText}
            value={field.value ?? null}
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
            onChange={(value, formattedValue) => {
              if (onChangeOverride) {
                onChangeOverride(value, formattedValue, field.onChange);
                return;
              }

              field.onChange(value);

              if (onChange) {
                onChange(value, formattedValue);
              }
            }}
          />
        );

        if (labelMode === 'above') {
          return (
            <Flex column fw>
              <FormLabel error={!!error} required={required}>
                {label}
              </FormLabel>
              {formControl}
            </Flex>
          );
        }

        return formControl;
      }}
    />
  );
}
