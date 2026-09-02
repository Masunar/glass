import type { FormCheckboxGroupProps } from './types.d';
import { Controller } from 'react-hook-form';

import { CheckboxGroup } from '@salvon/components/checkbox-group';
import { Div } from '@salvon/components/div';
import { useCurrentForm } from '@salvon/hooks/useForm';
import { isArray } from '@salvon/utils/type-check';

export default function FormCheckboxGroup<T extends string | number>({
  name,
  rules,
  onChange,
  defaultValue,
  helperText,
  options,
  disabled,
  label,
  ...props
}: FormCheckboxGroupProps<T>) {
  const { control } = useCurrentForm();

  if (defaultValue && !isArray(defaultValue)) {
    defaultValue = [defaultValue];
  }

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
            <CheckboxGroup
              {...props}
              disabled={disabled ?? field.disabled}
              label={label}
              error={error?.message}
              helperText={helperText}
              options={options}
              onChange={(v, cv) => {
                field.onChange(onChange ? (onChange(v, cv) ?? v) : v);
              }}
              value={field.value ?? []}
            />
          </Div>
        );
      }}
    />
  );
}
