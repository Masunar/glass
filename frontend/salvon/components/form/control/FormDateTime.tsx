import { FormLabel } from '@mui/material';
import { DateTimePicker } from '@mui/x-date-pickers';

import type { FormDateTimeProps } from './types.d';
import { Controller } from 'react-hook-form';
import { PiCalendarBlank } from 'react-icons/pi';

import { Flex } from '@salvon/components/div';
import { useCurrentForm } from '@salvon/hooks/useForm';
import { usePalette } from '@salvon/hooks/useTheme';

export default function FormDateTime({
  name,
  helperText,
  rules,
  onChange,
  onBlur,
  defaultValue,
  disabled,
  onChangeOverride,
  onBlurOverride,
  slotProps,
  localeText,
  labelMode,
  label,
  required,
  views = ['year', 'month', 'day', 'hours', 'minutes'],
  ...props
}: FormDateTimeProps) {
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
          <DateTimePicker
            format="DD.MM.YYYY HH:mm"
            ampm={false}
            {...props}
            localeText={{
              toolbarTitle: '',
              ...localeText,
            }}
            views={views}
            disabled={disabled || field.disabled}
            value={field.value ?? null}
            slots={{
              openPickerIcon: PiCalendarBlank,
              ...props.slots,
            }}
            slotProps={{
              ...slotProps,
              openPickerButton: {
                size: 'small',
                disableRipple: true,
                sx: { '&:hover': { backgroundColor: 'transparent' } },
                ...slotProps?.openPickerButton,
              },
              textField: {
                size: 'small',
                ...slotProps?.textField,
                label: labelMode === 'above' ? undefined : label,
                error: !!error,
                helperText: error ? error?.message : helperText,
                required: required,
              },
              field: {
                ...slotProps?.field,
                onBlur: (event) => {
                  if (onBlurOverride) {
                    onBlurOverride(event, field.onBlur);
                    return;
                  }

                  field.onBlur();

                  if (onBlur) {
                    onBlur(event);
                  }
                },
              },
            }}
            onChange={(value, context) => {
              if (onChangeOverride) {
                onChangeOverride(value, context, field.onChange);
                return;
              }

              field.onChange(
                onChange ? (onChange(value, context) ?? value) : value,
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
