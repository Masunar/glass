import type { FormSwitchProps } from './types.d';
import { Controller, useFormContext } from 'react-hook-form';

import { SwitchLabel } from '@salvon/components/label';
import { Switch } from '@salvon/components/switch';

export default function FormSwitch({
  name,
  label,
  helperText,
  rules,
  onChange,
  onBlur,
  slotProps,
  disabled,
  required,
  onChangeOverride,
  onBlurOverride,
  defaultValue,
  variant,
}: FormSwitchProps) {
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
          <Switch
            {...slotProps?.control}
            variant={variant}
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
            <SwitchLabel
              error={error?.message}
              label={label}
              helperText={helperText}
              wrapper={slotProps?.wrapper}
              formControlLabel={slotProps?.formControlLabel}
              formHelperText={slotProps?.formHelperText}
            >
              {controlElement}
            </SwitchLabel>
          </>
        );
      }}
    />
  );
}
