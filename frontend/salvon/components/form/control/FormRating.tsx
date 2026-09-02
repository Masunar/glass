import type { FormRatingProps } from './types.d';
import { Controller } from 'react-hook-form';

import { Rating } from '@salvon/components/rating';
import { useCurrentForm } from '@salvon/hooks/useForm';

export default function FormRating({
  name,
  rules,
  onChange,
  defaultValue,
  disabled,
  onBlur,
  ...props
}: FormRatingProps) {
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
          <Rating
            value={field.value}
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
