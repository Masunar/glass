import type { FormRadioGroupProps } from './types.d';
import { Controller } from 'react-hook-form';

import { Div } from '@salvon/components/div';
import { RadioGroup } from '@salvon/components/radio-group';
import { useCurrentForm } from '@salvon/hooks/useForm';

export default function FormRadioGroup<T extends string | number>({
  name,
  rules,
  onChange,
  defaultValue,
  helperText,
  options,
  disabled,
  label,
  ...props
}: FormRadioGroupProps<T>) {
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
          <Div>
            <RadioGroup
              {...props}
              disabled={disabled ?? field.disabled}
              label={label}
              error={error?.message}
              helperText={helperText}
              options={options}
              onChange={(v: T) => {
                field.onChange(onChange ? (onChange(v) ?? v) : v);
              }}
              value={field.value ?? []}
            />
          </Div>
        );
      }}
    />
  );
}
