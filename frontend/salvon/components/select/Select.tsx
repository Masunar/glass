import {
  Autocomplete,
  Checkbox,
  Chip,
  CircularProgress,
  TextField,
} from '@mui/material';

import type { SelectProps } from './types/Select.d';

import { Div, Flex } from '@salvon/components/div';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { isArray, isString } from '@salvon/utils/type-check';

export default function Select<T extends string | number>({
  options,
  loading,
  label,
  slotProps,
  onChange,
  multiple,
  value,
  maxOptions,
  multipleTagsLimit,
  checkboxes,
  renderValue,
  required,
  error,
  helperText,
  disabled,
  onBlur,
  defaultValue,
  placeholder,
  clearable = true,
  closeOnSelect = !multiple,
  fullWidth = true,
}: SelectProps<T>) {
  const t = useTranslation();

  const { autocomplete, textField, textFieldInput, optionCheckbox } =
    slotProps ?? {};

  const currentValue = (() => {
    if (typeof value === 'undefined') {
      return undefined;
    }

    if (isArray(value)) {
      return options.filter((o) => value.includes(o.value as any));
    }

    return options.find((o) => o.value === value) ?? (multiple ? [] : null);
  })();

  const defaultRenderValue = multiple
    ? (value: any, getItemProps: any) => {
        if (!isArray(value)) {
          return value.label;
        }

        return (
          <Flex wrap sx={{ overflow: 'hidden' }}>
            {value.map((v, index) => (
              <Chip
                {...getItemProps({ index })}
                color="primary"
                key={index}
                size="small"
                sx={{
                  fontSize: '0.75rem',
                  borderRadius: '4px',
                }}
                label={v.label}
              />
            ))}
          </Flex>
        );
      }
    : undefined;

  return (
    <Autocomplete
      defaultValue={defaultValue}
      disabled={disabled}
      limitTags={multipleTagsLimit}
      noOptionsText={t('no_results')}
      loadingText={t('loading')}
      renderOption={(props, option, { selected }) => (
        <li {...props} key={option.value}>
          {checkboxes && (
            <Checkbox checked={selected} size="small" {...optionCheckbox} />
          )}
          {option.label}
        </li>
      )}
      {...autocomplete}
      disableClearable={!clearable}
      disableCloseOnSelect={!closeOnSelect}
      renderValue={renderValue ? renderValue : defaultRenderValue}
      fullWidth={fullWidth}
      getOptionDisabled={(o) => {
        const isDisabled = o?.disabled ?? false;

        if (maxOptions && isArray(value) && value.length >= maxOptions) {
          return !value.includes(o.value) || isDisabled;
        }

        return isDisabled;
      }}
      value={currentValue}
      isOptionEqualToValue={(option: any, value) => {
        if (isArray(value)) {
          return value.find((v: any) => v.value === option?.value);
        }

        return option?.value === value?.value;
      }}
      onChange={
        typeof onChange === 'undefined'
          ? undefined
          : (event, option, reason, details) => {
              const selectedValue = isArray(option)
                ? option.map((o) => o.value)
                : (option?.value ?? null);

              onChange(selectedValue, option, {
                event,
                reason,
                details,
              });
            }
      }
      getOptionLabel={(o: any) => {
        if (isString(o)) {
          return o;
        }

        return o.label;
      }}
      loading={loading}
      options={options as any}
      multiple={multiple}
      renderInput={(params) => (
        <TextField
          {...params}
          {...textField}
          label={label}
          required={required}
          error={error}
          helperText={helperText}
          onBlur={onBlur}
          placeholder={placeholder}
          slotProps={{
            ...params.slotProps,
            input: {
              ...textFieldInput,
              ...params.slotProps.input,
              endAdornment: (
                <>
                  {loading && <CircularProgress color="inherit" size={20} />}
                  {params.slotProps.input.endAdornment}
                </>
              ),
            },
          }}
        />
      )}
    />
  );
}
