import { FormLabel } from '@mui/material';

import type { FormSelectProps } from './types.d';
import { Controller } from 'react-hook-form';

import { Flex } from '@salvon/components/div';
import { Select } from '@salvon/components/select';
import { useCurrentForm } from '@salvon/hooks/useForm';
import { usePalette } from '@salvon/hooks/useTheme';
import { isArray } from '@salvon/utils/type-check';

export default function FormSelect<T extends string | number>({
  name,
  rules,
  onChange,
  defaultValue,
  helperText,
  onBlur,
  options,
  disabled,
  onChangeOverride,
  onBlurOverride,
  labelMode,
  label,
  required,
  ...props
}: FormSelectProps<T>) {
  const { control } = useCurrentForm();
  const palette = usePalette();
  labelMode = labelMode ?? palette.salvon?.form?.labelMode ?? 'material';

  if (props.multiple && !isArray(defaultValue)) {
    defaultValue = [defaultValue] as T[];
  }

  return (
    <Controller
      name={name}
      control={control}
      rules={rules}
      defaultValue={defaultValue}
      render={({ field, fieldState }) => {
        const { error } = fieldState;

        const formControl = (
          <Select
            fullWidth
            {...props}
            label={labelMode === 'above' ? undefined : label}
            disabled={disabled || field.disabled}
            options={options}
            error={!!error}
            helperText={error ? error?.message : helperText}
            value={field.value ?? ''}
            required={required}
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
            onChange={(value: any, option: any, event) => {
              if (onChangeOverride) {
                onChangeOverride(value, option, event, field.onChange);
                return;
              }

              field.onChange(
                onChange ? (onChange(value, option, event) ?? value) : value,
              );
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
