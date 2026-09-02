import {
  ToggleButton,
  ToggleButtonGroup,
  type ToggleButtonGroupProps,
  type ToggleButtonProps,
} from '@mui/material';

import { type MouseEvent, type ReactNode, useState } from 'react';

import { isArray } from '@salvon/utils/type-check';

type OnChangeHandler<T extends string | number> = {
  onChange?: (value: T, event: MouseEvent<HTMLElement>) => void;
};

type OnChangeArrayableHandler<T extends string | number> = {
  onChange?: (value: T[], event: MouseEvent<HTMLElement>) => void;
};

type ValueProps<T extends string | number> =
  | ({
      multiple: true;
      value?: T[] | null;
      defaultValue?: T[] | null;
    } & OnChangeArrayableHandler<T>)
  | ({
      multiple?: false | undefined;
      value?: T | null;
      defaultValue?: T | null;
    } & OnChangeHandler<T>);

type Option<T extends string | number> = Omit<
  ToggleButtonProps,
  'children' | 'value'
> & { label: ReactNode; value: T };

export type ToggleGroupProps<T extends string | number> = {
  options: Option<T>[];
  multiple?: boolean;
  disableRipple?: boolean;
} & ValueProps<T> &
  Omit<
    ToggleButtonGroupProps,
    'value' | 'onChange' | 'defaultValue' | 'exclusive'
  >;
export default function ToggleGroup<T extends string | number>({
  options,
  value,
  defaultValue,
  onChange,
  size = 'small',
  multiple = false,
  disableRipple = true,
  ...props
}: ToggleGroupProps<T>) {
  if (defaultValue && !isArray(defaultValue)) {
    defaultValue = [defaultValue];
  }

  const [internalValue, setInternalValue] = useState<T[]>(
    defaultValue ?? ([] as any),
  );

  const val = value ?? internalValue;

  return (
    <ToggleButtonGroup
      {...props}
      size={size}
      className="disable-salvon-animate-all"
      exclusive={!multiple}
      value={val}
      onChange={(e, v) => {
        if (onChange) {
          onChange(v, e);
        }

        if (!value) {
          setInternalValue(v);
        }
      }}
    >
      {options.map(({ label, ...o }, k) => (
        <ToggleButton disableRipple={disableRipple} key={k} {...o}>
          {label}
        </ToggleButton>
      ))}
    </ToggleButtonGroup>
  );
}
