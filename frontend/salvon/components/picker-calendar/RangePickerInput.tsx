import { IconButton, InputAdornment, TextField } from '@mui/material';

import RangePickerCalendar from './RangePickerCalendar';
import { getRangeSections, sectionAtCaret, stepValue } from './sectionStep';
import type { DateRange, RangePickerInputProps } from './types.d';
import dayjs, { type Dayjs } from 'dayjs';
import customParseFormat from 'dayjs/plugin/customParseFormat';
import { useLayoutEffect, useRef, useState } from 'react';
import { PiCalendarBlank, PiX } from 'react-icons/pi';

import { Popover } from '@salvon/components/popover';
import { useCurrentLocale } from '@salvon/hooks/useLocale';

dayjs.extend(customParseFormat);

const EMPTY: DateRange = { start: null, end: null };

export default function RangePickerInput({
  fw = true,
  label,
  required,
  error,
  helperText,
  disabled,
  clearable = true,
  separator = ' - ',
  displayFormat = 'DD.MM.YYYY',
  onBlur,
  slotProps,
  value,
  defaultValue = null,
  onChange,
  onApply,
  onClear,
  locale: localeProp,
  format = 'D MMM YYYY',
  ...pickerProps
}: RangePickerInputProps) {
  const appLocale = useCurrentLocale();
  const locale = localeProp ?? appLocale;

  const anchorRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);
  const [open, setOpen] = useState(false);
  const [internal, setInternal] = useState<DateRange>(defaultValue ?? EMPTY);
  const current = value !== undefined ? (value ?? EMPTY) : internal;

  // Caret selection to restore after an arrow-step re-render.
  const pendingSelection = useRef<[number, number] | null>(null);
  useLayoutEffect(() => {
    const sel = pendingSelection.current;
    if (sel && inputRef.current) {
      inputRef.current.setSelectionRange(sel[0], sel[1]);
      pendingSelection.current = null;
    }
  });

  // Text being typed; null means "mirror the committed value".
  const [draft, setDraft] = useState<string | null>(null);

  const has = current.start || current.end;
  const formatted = has
    ? [
        current.start
          ? current.start.locale(locale).format(displayFormat)
          : displayFormat,
        current.end
          ? current.end.locale(locale).format(displayFormat)
          : displayFormat,
      ].join(separator)
    : '';
  const displayed = draft ?? formatted;

  function commit(next: DateRange) {
    if (value === undefined) {
      setInternal(next);
    }
    onChange?.(next, {
      start: next.start ? next.start.locale(locale).format(displayFormat) : '',
      end: next.end ? next.end.locale(locale).format(displayFormat) : '',
    });
  }

  function parseEndpoint(text: string): Dayjs | null | undefined {
    const trimmed = text.trim();
    if (!trimmed || trimmed === displayFormat) {
      return null;
    }
    const parsed = dayjs(trimmed, displayFormat, true);
    return parsed.isValid() ? parsed : undefined; // undefined = invalid, keep old
  }

  function commitDraft(raw: string) {
    if (!raw.trim()) {
      commit(EMPTY);
      setDraft(null);
      return;
    }
    const sepIndex = raw.indexOf(separator);
    const startText = sepIndex === -1 ? raw : raw.slice(0, sepIndex);
    const endText =
      sepIndex === -1 ? '' : raw.slice(sepIndex + separator.length);

    const start = parseEndpoint(startText);
    const end = parseEndpoint(endText);

    let next: DateRange = {
      start: start === undefined ? current.start : start,
      end: end === undefined ? current.end : end,
    };
    // Keep start ≤ end.
    if (next.start && next.end && next.end.isBefore(next.start, 'day')) {
      next = { start: next.end, end: next.start };
    }
    commit(next);
    setDraft(null);
  }

  function stepSection(caret: number, delta: number) {
    const text = formatted || `${displayFormat}${separator}${displayFormat}`;
    const sections = getRangeSections(displayFormat, separator, text);
    const section = sectionAtCaret(sections, caret);
    if (!section) {
      return;
    }
    const endpoint =
      section.part === 'start'
        ? (current.start ?? dayjs().startOf('day'))
        : (current.end ?? current.start ?? dayjs().startOf('day'));
    const stepped = stepValue(endpoint, section.unit, delta);
    let next: DateRange =
      section.part === 'start'
        ? { start: stepped, end: current.end }
        : { start: current.start, end: stepped };
    if (next.start && next.end && next.end.isBefore(next.start, 'day')) {
      next = { start: next.end, end: next.start };
    }

    // Re-measure sections on the stepped value to restore the caret.
    const nextText = [
      next.start
        ? next.start.locale(locale).format(displayFormat)
        : displayFormat,
      next.end ? next.end.locale(locale).format(displayFormat) : displayFormat,
    ].join(separator);
    const nextSections = getRangeSections(displayFormat, separator, nextText);
    const target = nextSections[sections.indexOf(section)] ?? section;
    pendingSelection.current = [target.start, target.end];
    setDraft(null);
    commit(next);
  }

  return (
    <>
      <TextField
        ref={anchorRef}
        fullWidth={fw}
        label={label}
        placeholder={`${displayFormat}${separator}${displayFormat}`}
        required={required}
        error={error}
        helperText={helperText}
        disabled={disabled}
        size="small"
        value={displayed}
        inputRef={inputRef}
        onChange={(e) => setDraft(e.target.value)}
        onBlur={(e) => {
          commitDraft(e.target.value);
          onBlur?.(e);
        }}
        onKeyDown={(e) => {
          if (e.key === 'Enter') {
            commitDraft((e.target as HTMLInputElement).value);
            return;
          }
          if (e.key === 'ArrowUp' || e.key === 'ArrowDown') {
            e.preventDefault();
            const caret = (e.target as HTMLInputElement).selectionStart ?? 0;
            stepSection(caret, e.key === 'ArrowUp' ? 1 : -1);
          }
        }}
        {...slotProps?.textField}
        sx={{
          '& .clear': { visibility: 'hidden' },
          '&:hover .clear': { visibility: 'visible' },
          ...slotProps?.textField?.sx,
        }}
        slotProps={{
          ...slotProps?.textField?.slotProps,
          input: {
            sx: { pr: 1 },
            endAdornment: (
              <InputAdornment position="end">
                {clearable && has && !disabled && (
                  <IconButton
                    className="clear"
                    edge="end"
                    size="small"
                    aria-label="clear"
                    onClick={() => {
                      setDraft(null);
                      commit(EMPTY);
                      onClear?.();
                    }}
                    sx={{
                      fontSize: '18px',
                      borderRadius: '6px',
                      padding: '2px',
                      marginRight: '1px',
                    }}
                  >
                    <PiX />
                  </IconButton>
                )}
                <IconButton
                  edge="end"
                  size="small"
                  disabled={disabled}
                  onClick={() => setOpen(true)}
                  sx={{
                    fontSize: '17px',
                    borderRadius: '6px',
                    padding: '3px',
                    marginRight: '1px',
                  }}
                >
                  <PiCalendarBlank />
                </IconButton>
              </InputAdornment>
            ),
            ...slotProps?.textField?.slotProps?.input,
          },
        }}
      />

      <Popover
        open={open}
        setOpen={setOpen}
        anchorEl={anchorRef.current}
        placement="bottom-right"
        elevation={0}
        slotProps={{
          paper: { sx: { backgroundColor: 'transparent' } },
        }}
        {...slotProps?.popover}
      >
        <RangePickerCalendar
          {...pickerProps}
          format={format}
          locale={locale}
          value={current}
          onChange={(next) => {
            setDraft(null);
            commit(next);
          }}
          onApply={(next) => {
            onApply?.(next);
            setOpen(false);
          }}
          onClear={() => {
            onClear?.();
            setOpen(false);
          }}
        />
      </Popover>
    </>
  );
}
