import type { FormNativeInputProps } from './types.d';
import { Controller } from 'react-hook-form';

import { useCurrentForm } from '@salvon/hooks/useForm';

export default function FormNativeInput({
  name,
  rules,
  onChange,
  defaultValue,
  disabled,
  onChangeOverride,
  onBlur,
  onBlurOverride,
  beforeInput,
  afterInput,
  ...props
}: FormNativeInputProps) {
  const { control } = useCurrentForm();

  return (
    <Controller
      name={name}
      control={control}
      rules={rules}
      defaultValue={defaultValue}
      disabled={disabled}
      render={({ field, fieldState }) => {
        const { error } = fieldState;

        return (
          <>
            {beforeInput && beforeInput({ error: error?.message })}
            <input
              {...props}
              disabled={disabled || field.disabled}
              value={field.value ?? ''}
              onFocus={(event) => {
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
              data-has-error={!!error}
            />
            {afterInput && afterInput({ error: error?.message })}
          </>
        );
      }}
    />
  );
}
