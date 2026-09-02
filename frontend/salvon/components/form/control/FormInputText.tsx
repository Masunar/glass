import { FormLabel, TextField } from '@mui/material';

import type { FormInputTextProps } from './types.d';
import { Controller } from 'react-hook-form';

import { Flex } from '@salvon/components/div';
import { useCurrentForm } from '@salvon/hooks/useForm';
import { usePalette } from '@salvon/hooks/useTheme';

export default function FormInputText({
  name,
  helperText,
  rules,
  onChange,
  onBlur,
  defaultValue,
  disabled,
  onChangeOverride,
  onBlurOverride,
  label,
  labelMode,
  ...props
}: FormInputTextProps) {
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
          <TextField
            fullWidth
            {...props}
            label={labelMode === 'above' ? undefined : label}
            disabled={disabled || field.disabled}
            error={!!error}
            helperText={error ? error?.message : helperText}
            value={field.value ?? ''}
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
            onChange={(event) => {
              if (onChangeOverride) {
                onChangeOverride(event, field.onChange);
                return;
              }

              field.onChange(
                onChange
                  ? (onChange(event.target.value, event) ?? event)
                  : event,
              );
            }}
          />
        );

        if (labelMode === 'above') {
          return (
            <Flex column fw>
              <FormLabel error={!!error} required={props.required}>
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
