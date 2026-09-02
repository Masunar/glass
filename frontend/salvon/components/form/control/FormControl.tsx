import type { TextFieldVariants } from '@mui/material';

import { createElement } from 'react';

import {
  FormCheckbox,
  FormCheckboxGroup,
  type FormCheckboxGroupProps,
  type FormCheckboxProps,
  FormControlCard,
  type FormControlCardProps,
  FormDate,
  type FormDateProps,
  FormDateTime,
  type FormDateTimeProps,
  FormInputInteger,
  type FormInputIntegerProps,
  FormInputNumber,
  type FormInputNumberProps,
  FormInputPassword,
  FormInputText,
  type FormInputTextProps,
  FormNativeInput,
  type FormNativeInputProps,
  FormPickerInput,
  type FormPickerInputProps,
  FormPickerRange,
  type FormPickerRangeProps,
  FormRadioGroup,
  FormSelect,
  type FormSelectProps,
  FormSwitch,
  type FormSwitchProps,
  FormTime,
  type FormTimeProps,
} from '@salvon/components/form';
import FormRating from '@salvon/components/form/control/FormRating';
import FormSlider from '@salvon/components/form/control/FormSlider';
import type {
  FormRadioGroupProps,
  FormRatingProps,
  FormSliderProps,
} from '@salvon/components/form/control/types';
import type { SwitchVariant } from '@salvon/components/switch/Switch';

export type FormControlProps =
  | ({ variant: 'checkbox'; renderVariant?: string } & Omit<
      FormCheckboxProps,
      'variant'
    >)
  | ({
      variant: 'checkbox_group';
    } & FormCheckboxGroupProps<string | number>)
  | ({ variant: 'control_card' } & FormControlCardProps)
  | ({ variant: 'date' } & FormDateProps)
  | ({ variant: 'date_time' } & FormDateTimeProps)
  | ({ variant: 'integer'; renderVariant?: TextFieldVariants } & Omit<
      FormInputIntegerProps,
      'variant'
    >)
  | ({ variant: 'password'; renderVariant?: TextFieldVariants } & Omit<
      FormInputTextProps,
      'variant'
    >)
  | ({ variant: 'native' } & FormNativeInputProps)
  | ({ variant: 'picker_input' } & FormPickerInputProps)
  | ({ variant: 'picker_range' } & FormPickerRangeProps)
  | ({ variant: 'radio_group' } & FormRadioGroupProps<string | number>)
  | ({ variant: 'select' } & FormSelectProps<string | number>)
  | ({ variant: 'switch'; renderVariant?: SwitchVariant } & Omit<
      FormSwitchProps,
      'variant'
    >)
  | ({ variant: 'time' } & FormTimeProps)
  | ({ variant: 'text' | undefined; renderVariant?: TextFieldVariants } & Omit<
      FormInputTextProps,
      'variant'
    >)
  | ({ variant: 'slider' } & FormSliderProps)
  | ({ variant: 'rating' } & FormRatingProps)
  | ({ variant: 'number'; renderVariant?: TextFieldVariants } & Omit<
      FormInputNumberProps,
      'variant'
    >);

export default function FormControl({ variant, ...props }: FormControlProps) {
  if (variant === 'checkbox') {
    return createElement(FormCheckbox, props as FormCheckboxProps);
  }

  if (variant === 'checkbox_group') {
    return createElement(FormCheckboxGroup, {
      ...(props as FormCheckboxGroupProps<string | number>),
      //@ts-ignore
      variant: props.renderVariant,
    });
  }

  if (variant === 'control_card') {
    return createElement(FormControlCard, props as FormControlCardProps);
  }

  if (variant === 'date') {
    return createElement(FormDate, props as FormDateProps);
  }

  if (variant === 'date_time') {
    return createElement(FormDateTime, props as FormDateTimeProps);
  }

  if (variant === 'integer') {
    return createElement(FormInputInteger, {
      ...(props as FormInputIntegerProps),
      //@ts-ignore
      variant: props.renderVariant,
    });
  }

  if (variant === 'number') {
    return createElement(FormInputNumber, {
      ...(props as FormInputNumberProps),
      //@ts-ignore
      variant: props.renderVariant,
    });
  }

  if (variant === 'password') {
    return createElement(FormInputPassword, {
      ...(props as FormInputTextProps),
      //@ts-ignore
      variant: props.renderVariant,
    });
  }

  if (variant === 'native') {
    return createElement(FormNativeInput, props as FormNativeInputProps);
  }

  if (variant === 'picker_input') {
    return createElement(FormPickerInput, props as FormPickerInputProps);
  }

  if (variant === 'picker_range') {
    return createElement(FormPickerRange, props as FormPickerRangeProps);
  }

  if (variant === 'radio_group') {
    return createElement(
      FormRadioGroup,
      props as FormRadioGroupProps<string | number>,
    );
  }

  if (variant === 'select') {
    return createElement(FormSelect, props as FormSelectProps<string | number>);
  }

  if (variant === 'switch') {
    return createElement(FormSwitch, {
      ...(props as FormSwitchProps),
      //@ts-ignore
      variant: props.renderVariant,
    });
  }

  if (variant === 'time') {
    return createElement(FormTime, props as FormTimeProps);
  }

  if (variant === 'slider') {
    return createElement(FormSlider, props as FormSliderProps);
  }

  if (variant === 'rating') {
    return createElement(FormRating, props as FormRatingProps);
  }

  return createElement(FormInputText, {
    ...(props as FormInputTextProps),
    //@ts-ignore
    variant: props.renderVariant,
  });
}
