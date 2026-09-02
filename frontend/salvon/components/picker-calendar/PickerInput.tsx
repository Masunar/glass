import { IconButton, InputAdornment, TextField } from '@mui/material';

import PickerCalendar from './PickerCalendar';
import { getSections, sectionAtCaret, stepValue } from './sectionStep';
import type { PickerInputProps } from './types.d';
import dayjs, { type Dayjs } from 'dayjs';
import customParseFormat from 'dayjs/plugin/customParseFormat';
import { useLayoutEffect, useRef, useState } from 'react';
import { PiCalendarBlank, PiX } from 'react-icons/pi';

import { Popover } from '@salvon/components/popover';
import { useCurrentLocale } from '@salvon/hooks/useLocale';

dayjs.extend(customParseFormat);

export default function PickerInput({
  fw = true,
  label,
  required,
  error,
  helperText,
  disabled,
  displayFormat = 'DD.MM.YYYY',
  clearable = true,
  onBlur,
  slotProps,
  value,
  defaultValue = null,
  onChange,
  onApply,
  onClear,
  locale: localeProp,
  format,
  ...pickerProps
}: PickerInputProps) {
  const appLocale = useCurrentLocale();
  const locale = localeProp ?? appLocale;

  const anchorRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);
  const [open, setOpen] = useState(false);

  const [internalValue, setInternalValue] = useState<Dayjs | null>(
    defaultValue,
  );
  const current = value !== undefined ? value : internalValue;

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

  const formatted = current ? current.locale(locale).format(displayFormat) : '';
  const displayed = draft ?? formatted;

  function commit(next: Dayjs | null) {
    if (value === undefined) {
      setInternalValue(next);
    }
    onChange?.(next, next ? next.locale(locale).format(displayFormat) : '');
  }

  function commitDraft(raw: string) {
    const trimmed = raw.trim();
    if (!trimmed) {
      commit(null);
      setDraft(null);
      return;
    }
    const parsed = dayjs(trimmed, displayFormat, true);
    if (parsed.isValid()) {
      // Keep the time part when only a date is typed.
      const merged = current
        ? parsed
            .hour(current.hour())
            .minute(current.minute())
            .second(current.second())
        : parsed;
      commit(merged);
    }
    // Invalid input reverts to the committed value.
    setDraft(null);
  }

  function stepSection(caret: number, delta: number) {
    const base = current ?? dayjs().startOf('day');
    const text = base.locale(locale).format(displayFormat);
    const sections = getSections(displayFormat, text);
    const section = sectionAtCaret(sections, caret);
    if (!section) {
      return;
    }
    const next = stepValue(base, section.unit, delta);
    // Re-measure sections on the stepped value (width may change, e.g. 9→10).
    const nextText = next.locale(locale).format(displayFormat);
    const nextSections = getSections(displayFormat, nextText);
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
        placeholder={displayFormat}
        required={required}
        error={error}
        helperText={helperText}
        disabled={disabled}
        size="small"
        value={displayed}
        inputRef={inputRef}
        sx={{
          '& .clear': { visibility: 'hidden' },
          '&:hover .clear': { visibility: 'visible' },
        }}
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
        slotProps={{
          ...slotProps?.textField?.slotProps,
          input: {
            sx: { pr: '6px' },
            endAdornment: (
              <InputAdornment position="end">
                {clearable && current && !disabled && (
                  <IconButton
                    className="clear"
                    edge="end"
                    size="small"
                    aria-label="clear"
                    onClick={() => {
                      setDraft(null);
                      commit(null);
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
        <PickerCalendar
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
