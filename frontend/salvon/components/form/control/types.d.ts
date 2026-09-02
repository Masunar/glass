import type {
  CheckboxProps,
  FormControlLabelProps,
  FormHelperTextProps,
  TextFieldProps,
} from '@mui/material';
import type {
  DatePickerProps,
  DateTimePickerProps,
  DateTimeValidationError,
  DateValidationError,
  MobileTimePickerProps,
  PickerChangeHandlerContext,
  TimeValidationError,
} from '@mui/x-date-pickers';
import type { PickerValue } from '@mui/x-date-pickers/internals';

import type { Dayjs } from 'dayjs';
import type { ChangeEvent, InputHTMLAttributes, ReactNode } from 'react';
import type { RegisterOptions } from 'react-hook-form/dist/types/validator';

import type { CheckboxGroupProps } from '@salvon/components/checkbox-group';
import type { ControlCardProps } from '@salvon/components/control-card';
import type {
  DateRange,
  PickerInputProps,
  RangePickerInputProps,
} from '@salvon/components/picker-calendar';
import type { RadioGroupProps } from '@salvon/components/radio-group';
import type { RatingProps } from '@salvon/components/rating';
import type { SelectProps } from '@salvon/components/select';
import type { EventChangeHandler } from '@salvon/components/select/types/Select';
import type { SliderProps } from '@salvon/components/slider';
import type { SwitchProps } from '@salvon/components/switch';
import type { Noop, SlotItem } from '@salvon/types';

type LabelMode = {
  labelMode?: 'material' | 'above';
};

export type ControllerRulesProp = Omit<
  RegisterOptions<TFieldValues, TName>,
  'valueAsNumber' | 'valueAsDate' | 'setValueAs' | 'disabled'
>;

export type FormNativeInputProps = Omit<
  InputHTMLAttributes<HTMLInputElement>,
  'name' | 'value' | 'onChange'
> & {
  name: string;
  rules?: ControllerRulesProp;
  defaultValue?: any;
  disabled?: boolean;
  onChange?: (
    value: string,
    event: ChangeEvent<HTMLInputElement>,
  ) => void | string;
  onChangeOverride?: (
    event: ChangeEvent<HTMLInputElement>,
    fieldOnChange: (...event: any[]) => void,
  ) => void;
  onBlur?: (event: FocusEvent<HTMLInputElement>) => void;
  onBlurOverride?: (
    event: FocusEvent<HTMLInputElement>,
    fieldOnBlur: Noop,
  ) => void;
  afterInput?: ({ error }: { error?: string }) => ReactNode;
  beforeInput?: ({ error }: { error?: string }) => ReactNode;
};

export type FormInputTextProps = Omit<
  TextFieldProps,
  'name' | 'value' | 'onChange' | 'onInput'
> & {
  name: string;
  rules?: ControllerRulesProp;
  defaultValue?: any;
  disabled?: boolean;
  onChange?: (
    value: string,
    event: ChangeEvent<HTMLInputElement | HTMLTextAreaElement>,
  ) => void | string | number;
  onChangeOverride?: (
    event: ChangeEvent<HTMLInputElement | HTMLTextAreaElement>,
    fieldOnChange: (...event: any[]) => void,
  ) => void;
  onBlurOverride?: (
    event: FocusEvent<HTMLInputElement | HTMLTextAreaElement>,
    fieldOnBlur: Noop,
  ) => void;
  onInput?: (event: InputEvent<HTMLInputElement | HTMLTextAreaElement>) => void;
} & LabelMode;

export type FormInputIntegerProps = Omit<FormInputTextProps, 'type'> & {
  max?: number;
  min?: number;
};

export type FormBooleanLikeItemProps<T extends SwitchProps | CheckboxProps> =
  Omit<
    SwitchProps,
    'name' | 'value' | 'defaultValue' | 'slotProps' | 'onChange'
  > & {
    name: string;
    rules?: ControllerRulesProp;
    label?: ReactNode;
    helperText?: ReactNode;
    defaultValue?: boolean;
    disabled?: boolean;
    required?: boolean;
    slotProps?: FormBooleanItemSharedSlotProps & {
      wrapper?: Omit<SlotItem<FormHelperTextProps>, 'error'>;
      formHelperText?: Omit<SlotItem<FormHelperTextProps>, 'error'>;
      formControlLabel?: SlotItem<FormControlLabelProps>;
      control?: Omit<SlotItem<T>, 'checked' | 'onChange' | 'onBlur'>;
    };
    onChange?: (
      checked: boolean,
      event: ChangeEvent<HTMLInputElement>,
    ) => void | boolean;
    onChangeOverride?: (
      event: ChangeEvent<HTMLInputElement>,
      checked: boolean,
      fieldOnChange: (...event: any[]) => void,
    ) => void;
    onBlurOverride?: (
      event: FocusEvent<HTMLButtonElement>,
      fieldOnBlur: Noop,
    ) => void;
  };

export type FormSwitchProps = FormBooleanLikeItemProps<SwitchProps>;
export type FormCheckboxProps = Omit<
  FormBooleanLikeItemProps<CheckboxProps>,
  'variant'
>;

export type FormControlCardProps = Omit<
  ControlCardProps,
  'checked' | 'onClick' | 'control'
> & {
  name: string;
  rules?: ControllerRulesProp;
  defaultValue?: boolean;
  disabled?: boolean;
  control?: ControlCardProps['control'];
  onChange?: (checked: boolean) => void | boolean;
  onChangeOverride?: (
    checked: boolean,
    fieldOnChange: (...event: any[]) => void,
  ) => void;
};

export type FormSelectProps<T> = Omit<
  SelectProps<T>,
  'name' | 'value' | 'error' | 'defaultValue' | 'onChange'
> & {
  name: string;
  rules?: ControllerRulesProp;
  defaultValue?: T | T[];
  onChange?: (
    value: T | T[],
    option: T | T[],
    event: EventChangeHandler,
  ) => void;
  onChangeOverride?: (
    value: T | T[],
    option: T | T[],
    event: EventChangeHandler,
    fieldOnChange: (...event: any[]) => void,
  ) => void;
  onBlurOverride?: (
    event: FocusEvent<HTMLInputElement | HTMLTextAreaElement>,
    fieldOnBlur: Noop,
  ) => void;
} & LabelMode;

export type FormCheckboxGroupProps<T> = Omit<
  CheckboxGroupProps<T>,
  'value' | 'error'
> & {
  name: string;
  rules?: ControllerRulesProp;
  defaultValue?: T | T[];
};

export type FormRadioGroupProps<T> = Omit<
  RadioGroupProps<T>,
  'value' | 'error'
> & {
  name: string;
  rules?: ControllerRulesProp;
  defaultValue?: T;
};

type MuiPickerRhfProps<T> = {
  name: string;
  rules?: ControllerRulesProp;
  defaultValue?: any;
  disabled?: boolean;
  helperText?: string;
  onChange?: (
    value: PickerValue,
    context: PickerChangeHandlerContext<T>,
  ) => void | string;
  onChangeOverride?: (
    value: PickerValue,
    context: PickerChangeHandlerContext<T>,
    fieldOnChange: (...event: any[]) => void,
  ) => void;
  onBlurOverride?: (
    event: FocusEvent<HTMLDivElement>,
    fieldOnBlur: Noop,
  ) => void;
  onBlur?: (event: FocusEvent<HTMLDivElement>) => void;
  required?: boolean;
} & LabelMode;

export type FormDateProps = Omit<
  DatePickerProps,
  'name' | 'value' | 'onChange'
> &
  MuiPickerRhfProps<DateValidationError>;

export type FormTimeProps = Omit<
  MobileTimePickerProps,
  'name' | 'value' | 'onChange'
> &
  MuiPickerRhfProps<TimeValidationError>;

export type FormDateTimeProps = Omit<
  DateTimePickerProps,
  'name' | 'value' | 'onChange'
> &
  MuiPickerRhfProps<DateTimeValidationError>;

export type FormSliderProps = Omit<
  SliderProps,
  'value' | 'error' | 'onChange'
> & {
  name: string;
  rules?: ControllerRulesProp;
  defaultValue?: T | T[];
  onChange?: (
    value: number | number[],
    event: ChangeEvent<HTMLElement>,
  ) => void | string;
};

export type FormRatingProps = Omit<
  RatingProps,
  'value' | 'error' | 'onChange'
> & {
  name: string;
  rules?: ControllerRulesProp;
  defaultValue?: T | T[];
  onChange?: (
    value: number | number[],
    event: ChangeEvent<HTMLElement>,
  ) => void | string;
};

export type FormInputNumberProps = Omit<FormInputIntegerProps, 'type'> & {
  precision?: number;
};

export type FormPickerInputProps = Omit<
  PickerInputProps,
  'value' | 'defaultValue' | 'onChange' | 'error' | 'disabled'
> & {
  name: string;
  rules?: ControllerRulesProp;
  defaultValue?: Dayjs | null;
  disabled?: boolean;
  onChange?: (value: Dayjs | null, formattedValue: string) => void;
  onChangeOverride?: (
    value: Dayjs | null,
    formattedValue: string,
    fieldOnChange: (...event: any[]) => void,
  ) => void;
  onBlur?: (event: FocusEvent<HTMLInputElement>) => void;
  onBlurOverride?: (
    event: FocusEvent<HTMLInputElement>,
    fieldOnBlur: Noop,
  ) => void;
} & LabelMode;

export type FormPickerRangeProps = Omit<
  RangePickerInputProps,
  'value' | 'defaultValue' | 'onChange' | 'error' | 'disabled'
> & {
  name: string;
  rules?: ControllerRulesProp;
  defaultValue?: DateRange | null;
  disabled?: boolean;
  onChange?: (
    value: DateRange,
    formattedValue: { start: string; end: string },
  ) => void;
  onChangeOverride?: (
    value: DateRange,
    formattedValue: { start: string; end: string },
    fieldOnChange: (...event: any[]) => void,
  ) => void;
  onBlur?: (event: FocusEvent<HTMLInputElement>) => void;
  onBlurOverride?: (
    event: FocusEvent<HTMLInputElement>,
    fieldOnBlur: Noop,
  ) => void;
} & LabelMode;
