import { Box } from '@mui/material';

import { getSections, sectionAtCaret, stepValue } from '../sectionStep';
import type { TimeView } from '../types.d';
import dayjs, { type Dayjs } from 'dayjs';
import customParseFormat from 'dayjs/plugin/customParseFormat';
import { useLayoutEffect, useMemo, useRef, useState } from 'react';
import { LuChevronDown, LuChevronUp, LuClock } from 'react-icons/lu';

import { Flex } from '@salvon/components/div';
import { IconButton } from '@salvon/components/icon-button';

dayjs.extend(customParseFormat);

type TimeStepInputProps = {
  value: Dayjs | null;
  timeViews: TimeView[];
  is12h?: boolean;
  disabled?: boolean;
  onChange: (value: Dayjs) => void;
};

const stepBtnSx = {
  width: 20,
  height: 16,
  minWidth: 20,
  p: 0,
  borderRadius: '4px',
  color: 'text.secondary',
  '&:hover': { color: 'text.primary', bgcolor: 'action.hover' },
  '& svg': { fontSize: 13 },
};

/**
 * Bordered time box with a clock icon, a segmented `HH:mm[:ss]` field and a
 * stacked up/down stepper — click/arrow a segment then type or step it.
 */
export default function TimeStepInput({
  value,
  timeViews,
  is12h = false,
  disabled,
  onChange,
}: TimeStepInputProps) {
  const showMeridiem = is12h && timeViews.includes('hours');
  const format = useMemo(() => {
    const parts: string[] = [];
    if (timeViews.includes('hours')) {
      parts.push(is12h ? 'hh' : 'HH');
    }
    if (timeViews.includes('minutes')) {
      parts.push('mm');
    }
    if (timeViews.includes('seconds')) {
      parts.push('ss');
    }
    return parts.join(':') || 'HH:mm';
  }, [timeViews, is12h]);

  const inputRef = useRef<HTMLInputElement>(null);
  const base = value ?? dayjs().startOf('day');
  const text = base.format(format);
  const isPm = base.hour() >= 12;

  function toggleMeridiem() {
    onChange(base.hour((base.hour() + 12) % 24));
  }

  // The section the stepper acts on — tracked so the buttons work even when
  // focus isn't in the input; defaults to the first (hours) section.
  const [caret, setCaret] = useState(0);

  const pendingSelection = useRef<[number, number] | null>(null);
  useLayoutEffect(() => {
    const sel = pendingSelection.current;
    if (sel && inputRef.current) {
      inputRef.current.setSelectionRange(sel[0], sel[1]);
      pendingSelection.current = null;
    }
  });

  const [draft, setDraft] = useState<string | null>(null);
  const shown = draft ?? text;

  function selectSection(section: { start: number; end: number }) {
    setCaret(section.start);
    inputRef.current?.setSelectionRange(section.start, section.end);
  }

  function selectSectionAt(at: number) {
    const section = sectionAtCaret(getSections(format, text), at);
    if (section) {
      selectSection(section);
    }
  }

  /** Move selection to the previous/next segment. */
  function moveSection(dir: 1 | -1) {
    const sections = getSections(format, text);
    const idx = sections.findIndex((s) => caret >= s.start && caret <= s.end);
    const next = sections[Math.min(Math.max(idx + dir, 0), sections.length - 1)];
    if (next) {
      selectSection(next);
    }
  }

  function step(delta: number) {
    const sections = getSections(format, text);
    const section = sectionAtCaret(sections, caret);
    if (!section) {
      return;
    }
    const next = stepValue(base, section.unit, delta);
    const nextSections = getSections(format, next.format(format));
    const target = nextSections[sections.indexOf(section)] ?? section;
    pendingSelection.current = [target.start, target.end];
    setDraft(null);
    onChange(next);
  }

  // Wheel-to-step the focused segment. React's onWheel is passive so we attach a
  // non-passive listener to preventDefault; a ref keeps `step` current.
  const [focused, setFocused] = useState(false);
  const stepRef = useRef(step);
  stepRef.current = step;
  useLayoutEffect(() => {
    const el = inputRef.current;
    if (!el) {
      return;
    }
    const onWheel = (e: WheelEvent) => {
      if (!focused || disabled) {
        return;
      }
      e.preventDefault();
      stepRef.current(e.deltaY < 0 ? 1 : -1);
    };
    el.addEventListener('wheel', onWheel, { passive: false });
    return () => el.removeEventListener('wheel', onWheel);
  }, [focused, disabled]);

  function commitDraft(raw: string) {
    const parsed = dayjs(raw.trim(), format, true);
    if (parsed.isValid()) {
      // In 12h mode the input omits AM/PM, so keep the current meridiem: map the
      // typed 12h hour (0–11 after parse) back into the 24h clock.
      const hour = showMeridiem
        ? (parsed.hour() % 12) + (isPm ? 12 : 0)
        : parsed.hour();
      onChange(
        base.hour(hour).minute(parsed.minute()).second(parsed.second()),
      );
    }
    setDraft(null);
  }

  return (
    <Flex
      aCenter
      gap={0.75}
      sx={{
        flex: 1,
        minWidth: 0,
        height: 34,
        pl: 1,
        pr: 0.5,
        borderRadius: '8px',
        border: '1px solid',
        borderColor: 'divider',
        transition: 'border-color 0.12s ease',
        opacity: disabled ? 0.5 : 1,
        '&:focus-within': {
          borderColor: 'primary.main',
          boxShadow: (t: any) => `0 0 0 1px ${t.palette.primary.main}`,
        },
      }}
    >
      <Flex aCenter sx={{ color: 'text.secondary', '& svg': { fontSize: 14 } }}>
        <LuClock />
      </Flex>

      <Box
        component="input"
        ref={inputRef}
        inputMode="numeric"
        disabled={disabled}
        value={shown}
        onChange={(e) => setDraft(e.target.value)}
        onMouseUp={(e) => selectSectionAt(e.currentTarget.selectionStart ?? 0)}
        onFocus={(e) => {
          setFocused(true);
          selectSectionAt(e.currentTarget.selectionStart ?? 0);
        }}
        onBlur={(e) => {
          setFocused(false);
          commitDraft(e.target.value);
        }}
        onKeyDown={(e) => {
          if (e.key === 'ArrowUp') {
            e.preventDefault();
            step(1);
          } else if (e.key === 'ArrowDown') {
            e.preventDefault();
            step(-1);
          } else if (e.key === 'ArrowLeft') {
            e.preventDefault();
            moveSection(-1);
          } else if (e.key === 'ArrowRight') {
            e.preventDefault();
            moveSection(1);
          } else if (e.key === 'Enter') {
            e.currentTarget.blur();
          }
        }}
        sx={{
          flex: 1,
          minWidth: 0,
          border: 'none',
          outline: 'none',
          bgcolor: 'transparent',
          color: 'text.primary',
          fontFamily: 'inherit',
          fontSize: '0.9rem',
          fontWeight: 600,
          letterSpacing: '0.02em',
          p: 0,
          '&::selection': {
            backgroundColor: 'primary.main',
            color: 'primary.contrastText',
          },
        }}
      />

      {showMeridiem && (
        <Flex
          center
          role="button"
          tabIndex={disabled ? -1 : 0}
          onMouseDown={(e) => e.preventDefault()}
          onClick={() => !disabled && toggleMeridiem()}
          onKeyDown={(e) => {
            if (e.key === 'Enter' || e.key === ' ') {
              e.preventDefault();
              toggleMeridiem();
            }
          }}
          sx={{
            px: 0.75,
            height: 24,
            borderRadius: '6px',
            fontSize: '0.72rem',
            fontWeight: 700,
            letterSpacing: '0.04em',
            cursor: disabled ? 'default' : 'pointer',
            color: 'text.secondary',
            bgcolor: 'action.hover',
            outline: 'none',
            userSelect: 'none',
            '&:hover': { color: 'text.primary' },
            '&:focus-visible': {
              boxShadow: (t: any) => `0 0 0 2px ${t.palette.primary.main}`,
            },
          }}
        >
          {isPm ? 'PM' : 'AM'}
        </Flex>
      )}

      <Flex column>
        <IconButton
          variant="mui"
          sx={stepBtnSx}
          disabled={disabled}
          onMouseDown={(e) => e.preventDefault()}
          onClick={() => step(1)}
        >
          <LuChevronUp />
        </IconButton>
        <IconButton
          variant="mui"
          sx={stepBtnSx}
          disabled={disabled}
          onMouseDown={(e) => e.preventDefault()}
          onClick={() => step(-1)}
        >
          <LuChevronDown />
        </IconButton>
      </Flex>
    </Flex>
  );
}
