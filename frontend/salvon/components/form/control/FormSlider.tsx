import type { FormNativeInputProps, FormSliderProps } from './types.d';
import { Controller } from 'react-hook-form';

import { Slider } from '@salvon/components/slider';
import { useCurrentForm } from '@salvon/hooks/useForm';

export default function FormSlider({
  name,
  rules,
  onChange,
  defaultValue,
  disabled,
  onBlur,
  ...props
}: FormSliderProps) {
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
          <Slider
            value={field.value ?? (!props.range ? 0 : [0, 100])}
            error={error?.message}
            onChange={(v) => {
              field.onChange(v);
            }}
            disabled={disabled || field.disabled}
            {...props}
          />
        );
      }}
    />
  );
}
