import type { FormControlCardProps } from './types.d';
import { Controller, useFormContext } from 'react-hook-form';

import { ControlCard } from '@salvon/components/control-card';

export default function FormControlCard({
  name,
  rules,
  defaultValue,
  disabled,
  control = 'checkbox',
  onChange,
  onChangeOverride,
  ...props
}: FormControlCardProps) {
  const { control: formControl } = useFormContext();

  return (
    <Controller
      name={name}
      control={formControl}
      rules={rules}
      defaultValue={defaultValue}
      disabled={disabled}
      render={({ field }) => {
        const checked = field.value || false;

        return (
          <ControlCard
            {...props}
            control={control}
            checked={checked}
            disabled={disabled || field.disabled}
            onClick={() => {
              const next = !checked;

              if (onChangeOverride) {
                onChangeOverride(next, field.onChange);
                return;
              }

              field.onChange(onChange ? (onChange(next) ?? next) : next);
            }}
          />
        );
      }}
    />
  );
}
