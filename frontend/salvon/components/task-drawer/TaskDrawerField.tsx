import { TextField, Typography } from '@mui/material';
import { DatePicker, DateTimePicker } from '@mui/x-date-pickers';

import EditControls from './EditControls';
import type { TaskDrawerField as FieldConfig } from './types.d';
import dayjs from 'dayjs';
import type { ReactNode } from 'react';

import { Flex } from '@salvon/components/div';
import { SimpleSelect } from '@salvon/components/select';
import { useInlineEdit } from '@salvon/hooks/useInlineEdit';
import { useIsDarkMode } from '@salvon/hooks/useTheme';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { voc } from '@salvon/utils/object';

type Props = { field: FieldConfig };

export default function TaskDrawerField({ field }: Props) {
  const t = useTranslation();
  const isDark = useIsDarkMode();

  const type = field.type ?? 'text';
  const isStatic = type === 'static';
  const editable = !isStatic && (field.editable ?? true);

  const { editing, begin, cancel, save, value, setValue, loading } =
    useInlineEdit(field.value, field.onSave);

  return (
    <Flex aCenter sx={{ gap: 3, minHeight: 40, minWidth: 0 }}>
      <Flex aCenter sx={{ gap: 1, flex: 1, minWidth: 0 }}>
        {field.icon && (
          <Flex
            aCenter
            sx={{ opacity: 0.55, flexShrink: 0, '& svg': { display: 'block' } }}
          >
            {field.icon}
          </Flex>
        )}
        <Typography sx={{ fontSize: 14 }} noWrap>
          {field.label}
        </Typography>
      </Flex>

      <Flex aCenter sx={{ flex: 1, minWidth: 0, gap: 1 }}>
        {editing ? (
          <FieldEditor
            field={field}
            value={value}
            setValue={setValue}
            save={save}
            cancel={cancel}
            loading={loading}
          />
        ) : (
          <Flex
            aCenter
            sx={{
              flex: 1,
              minWidth: 0,
              ...voc(editable, { cursor: 'pointer' }),
            }}
            onClick={() => editable && begin()}
          >
            <Typography
              component="span"
              sx={{
                fontSize: 14,
                px: 1,
                py: 0.5,
                borderRadius: '9px',
                minWidth: 0,
                ...voc(editable, {
                  cursor: 'text',
                  '&:hover': { backgroundColor: isDark ? '#222' : '#eee' },
                }),
              }}
            >
              {displayValue(field, t)}
            </Typography>
          </Flex>
        )}
      </Flex>
    </Flex>
  );
}

function isEmpty(value: any) {
  return value === null || value === undefined || value === '';
}

function displayValue(field: FieldConfig, t: (k: string) => string): ReactNode {
  const value = field.value;

  if (field.render) return field.render(value);

  if (isEmpty(value)) {
    return (
      <Typography
        component="span"
        sx={{ fontSize: 14, fontStyle: 'italic', color: 'text.disabled' }}
      >
        {field.placeholder ?? t('undefined')}
      </Typography>
    );
  }

  switch (field.type) {
    case 'date':
      return dayjs(value).format('DD.MM.YYYY');
    case 'datetime':
      return dayjs(value).format('DD.MM.YYYY HH:mm');
    case 'select':
      return (
        field.options.find((o) => o.value === value)?.label ?? String(value)
      );
    default:
      return String(value);
  }
}

type EditorProps = {
  field: FieldConfig;
  value: any;
  setValue: (value: any) => void;
  save: () => void;
  cancel: () => void;
  loading: boolean;
};

function FieldEditor({
  field,
  value,
  setValue,
  save,
  cancel,
  loading,
}: EditorProps) {
  const t = useTranslation();

  switch (field.type) {
    case 'custom':
      return (
        <>{field.renderEditor({ value, setValue, save, cancel, loading })}</>
      );

    case 'date':
    case 'datetime': {
      const pickerSlotProps: any = {
        field: {
          clearable: true,
          onClear: () => setValue(null),
          size: 'small',
          disabled: loading,
          sx: {
            flex: 1,
            '& .MuiPickersOutlinedInput-root': {
              borderRadius: '9px',
              fontSize: 14,
            },
            '& .MuiPickersOutlinedInput-input': { py: '7px' },
          },
        },
      };
      return (
        <Flex aCenter sx={{ flex: 1, gap: 0.5, minWidth: 0 }}>
          {field.type === 'date' ? (
            <DatePicker
              value={value ? dayjs(value) : null}
              onChange={(next) => setValue(next)}
              closeOnSelect
              slotProps={pickerSlotProps}
            />
          ) : (
            <DateTimePicker
              value={value ? dayjs(value) : null}
              onChange={(next) => setValue(next)}
              closeOnSelect={false}
              slotProps={pickerSlotProps}
            />
          )}
          <EditControls save={save} cancel={cancel} loading={loading} />
        </Flex>
      );
    }

    case 'select':
      return (
        <Flex aCenter sx={{ flex: 1, gap: 0.5, minWidth: 0 }}>
          <SimpleSelect
            size="small"
            value={value ?? ''}
            options={field.options}
            onChange={(event) => setValue(event.target.value)}
            slotProps={{
              select: {
                disabled: loading,
                sx: { borderRadius: '9px', fontSize: 14 },
              },
            }}
          />
          <EditControls save={save} cancel={cancel} loading={loading} />
        </Flex>
      );

    default: {
      const multiline = field.type === 'textarea';
      return (
        <TextField
          value={value ?? ''}
          disabled={loading}
          autoFocus
          multiline={multiline}
          size="small"
          placeholder={
            typeof field.placeholder === 'string'
              ? field.placeholder
              : undefined
          }
          onChange={(event) => setValue(event.target.value)}
          onBlur={save}
          onKeyDown={(event) => {
            if (event.key === 'Enter' && !multiline) {
              event.preventDefault();
              save();
            }
            if (event.key === 'Escape') cancel();
          }}
          sx={{
            flex: 1,
            '& .MuiInputBase-root': { borderRadius: '9px', fontSize: 14 },
          }}
          slotProps={{
            input: {
              endAdornment: (
                <EditControls save={save} cancel={cancel} loading={loading} />
              ),
            },
          }}
          aria-label={t('edit')}
        />
      );
    }
  }
}
