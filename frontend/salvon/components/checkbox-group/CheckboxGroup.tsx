import {
  Checkbox,
  FormHelperText,
  FormLabel,
  type FormLabelProps,
  Radio,
} from '@mui/material';

import { type ReactElement, type ReactNode, useState } from 'react';

import { ControlCard } from '@salvon/components/control-card';
import {
  Div,
  type DivProps,
  Flex,
  type FlexProps,
} from '@salvon/components/div';
import { CheckboxLabel } from '@salvon/components/label';
import type { SelectOption } from '@salvon/components/select';
import { Switch } from '@salvon/components/switch';
import type { SlotItem } from '@salvon/types';

export type Option<T extends string | number> = SelectOption<T> & {
  description?: ReactNode;
};

export type CheckboxGroupProps<T extends string | number> = {
  options: Option<T>[];
  value?: T[];
  label?: ReactNode;
  onChange?: (value: T[], currentValue: T[]) => T[] | void;
  vertical?: boolean;
  error?: string;
  disabled?: boolean;
  required?: boolean;
  helperText?: ReactNode;
  renderOption?: (
    option: Option<T>,
    handleChange: (option: T) => void,
  ) => ReactElement;
  slotProps?: {
    wrapper?: SlotItem<DivProps>;
    label?: FormLabelProps;
    flexGroup?: FlexProps;
  };
  component?: 'radio' | 'checkbox' | 'switch' | 'radio-card' | 'checkbox-card';
  optionError?: boolean;
};

export default function CheckboxGroup<T extends string | number>({
  label,
  options,
  value,
  onChange,
  renderOption,
  slotProps,
  error,
  helperText,
  disabled,
  required,
  vertical,
  optionError = true,
  component = 'checkbox',
}: CheckboxGroupProps<T>) {
  const [checked, setChecked] = useState<T[]>([]);

  const handleChange = (optValue: T) => {
    const val = value ?? checked;

    const newValue = val.includes(optValue)
      ? val.filter((v) => v !== optValue)
      : [...val, optValue];

    setChecked(onChange ? (onChange(newValue, val) ?? newValue) : newValue);
  };

  const optionVariant = (props: any) => {
    if (component === 'radio') {
      return <Radio {...props} />;
    }

    if (component === 'switch') {
      return <Switch {...props} />;
    }

    return <Checkbox {...props} />;
  };

  const isCard = component === 'radio-card' || component === 'checkbox-card';

  const render = renderOption
    ? renderOption
    : (o: Option<T>, handleOptChange: (o: T) => void, key: number) => {
        const controlProps = {
          onClick: () => handleOptChange(o.value),
          checked: (value ?? checked)?.includes(o.value),
          disabled: disabled ?? o.disabled,
        };

        if (isCard) {
          return (
            <ControlCard
              key={key}
              control={component === 'radio-card' ? 'radio' : 'checkbox'}
              label={o.label}
              description={o.description}
              {...controlProps}
            />
          );
        }

        return (
          <CheckboxLabel
            key={key}
            label={o.label}
            forceError={optionError && !!error}
          >
            <div>{optionVariant(controlProps)}</div>
          </CheckboxLabel>
        );
      };

  return (
    <Div {...slotProps?.wrapper}>
      {label && (
        <FormLabel {...slotProps?.label} error={!!error} required={required}>
          {label}
        </FormLabel>
      )}
      <Flex
        gap={isCard ? 1 : undefined}
        {...slotProps?.flexGroup}
        column={vertical}
      >
        {options.map((o, key) => render(o, handleChange, key))}
      </Flex>
      {(!!error || helperText) && (
        <FormHelperText error={!!error}>{error ?? helperText}</FormHelperText>
      )}
    </Div>
  );
}
