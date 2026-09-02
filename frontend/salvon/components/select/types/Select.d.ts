import type {
  AutocompleteProps,
  AutocompleteRenderValue,
  CheckboxProps,
  InputBaseProps,
  InputProps,
  TextFieldProps,
} from '@mui/material';
import type {
  AutocompleteOwnerState,
  AutocompleteRenderValueGetItemProps,
} from '@mui/material/Autocomplete/Autocomplete';

import type { ReactNode, SyntheticEvent } from 'react';

import type { SlotItem } from '@salvon/types';

export type SelectOption<T extends string | number> = {
  value: T;
  label: string | number;
  disabled?: boolean;
};

export type SelectValue<T> = T | null;
export type MultipleSelectValue<T> = Array<T>;

type EventChangeHandler = {
  event: SyntheticEvent;
  reason: any;
  details?: any;
};

type OnChangeHandler<T> = {
  onChange?: (
    value: T,
    option: SelectOption<T>,
    event: EventChangeHandler,
  ) => void;
};

type OnChangeArrayableHandler<T> = {
  onChange?: (
    value: Array<T>,
    option: Array<SelectOption<T>>,
    event: EventChangeHandler,
  ) => void;
};

type ValueProps<T> =
  | ({
      multiple: true;
      value?: Array<T> | null;
    } & OnChangeArrayableHandler<T>)
  | ({
      multiple?: false | undefined;
      value?: T | null;
    } & OnChangeHandler<T>);

export type SelectProps<T> = {
  options: Array<SelectOption<T>>;
  defaultValue?: Array<SelectOption<T>>;
  fullWidth?: boolean;
  loading?: boolean;
  checkboxes?: boolean;
  clearable?: boolean;
  closeOnSelect?: boolean;
  required?: boolean;
  error?: boolean;
  helperText?: ReactNode;
  label?: string;
  disabled?: boolean;
  maxOptions?: number;
  onBlur?: InputBaseProps['onBlur'];
  multipleTagsLimit?: number;
  placeholder?: string;
  slotProps?: {
    autocomplete?: SlotItem<AutocompleteProps<any, any, any, any>>;
    textFieldInput?: SlotItem<InputProps>;
    textField?: SlotItem<TextFieldProps>;
    optionCheckbox?: SlotItem<CheckboxProps>;
  };
  renderValue?: (
    value: AutocompleteRenderValue<any, any, any>,
    getItemProps: AutocompleteRenderValueGetItemProps,
    ownerState: AutocompleteOwnerState,
  ) => ReactNode;
} & ValueProps<T>;
