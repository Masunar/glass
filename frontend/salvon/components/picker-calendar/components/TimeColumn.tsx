import { Box } from '@mui/material';

import {
  type ChangeEvent,
  type KeyboardEvent,
  type ReactNode,
  useEffect,
  useRef,
  useState,
} from 'react';
import { LuChevronDown, LuChevronUp } from 'react-icons/lu';

import { Div, Flex } from '@salvon/components/div';
import { IconButton } from '@salvon/components/icon-button';

const stepBtnSx = {
  width: 30,
  height: 30,
  minWidth: 30,
  '& svg': { fontSize: 16 },
};

const boxSx = {
  width: 52,
  height: 46,
  borderRadius: '10px',
  border: '2px solid',
  fontSize: '1.25rem',
  fontWeight: 700,
  outline: 'none',
  transition: 'border-color 0.12s ease',
};

export type TimeUnit = {
  key: 'hour' | 'minute' | 'second';
  value: number;
  max: number;
  label: string;
  display?: (value: number) => string;
  parse?: (typed: number) => number;
};

type Meridiem = {
  value: 'am' | 'pm';
  label: string;
  onToggle: () => void;
};

type TimeColumnsProps = {
  units: TimeUnit[];
  meridiem?: Meridiem;
  title?: string;
  focusedKey?: TimeUnit['key'] | null;
  onChange: (key: TimeUnit['key'], value: number) => void;
  onFocus?: (key: TimeUnit['key']) => void;
  onBlur?: () => void;
};

const pad = (n: number) => String(n).padStart(2, '0');

export default function TimeColumns({
  units,
  meridiem,
  title,
  focusedKey,
  onChange,
  onFocus,
  onBlur,
}: TimeColumnsProps) {
  return (
    <Div>
      {title && (
        <Flex
          jCenter
          sx={{
            fontSize: '0.7rem',
            fontWeight: 700,
            letterSpacing: '0.06em',
            textTransform: 'uppercase',
            color: 'text.secondary',
            mb: 1.5,
          }}
        >
          {title}
        </Flex>
      )}

      <Flex jCenter aCenter gap={1}>
        {units.map((u, i) => (
          <Flex key={u.key} aCenter gap={1}>
            <NumberSpinner
              max={u.max}
              text={u.display ? u.display(u.value) : pad(u.value)}
              parse={u.parse}
              focused={focusedKey === u.key}
              label={u.label}
              onStep={(dir) => onChange(u.key, (u.value + dir + u.max) % u.max)}
              onSet={(v) => onChange(u.key, v)}
              onFocus={() => onFocus?.(u.key)}
              onBlur={() => onBlur?.()}
            />
            {i < units.length - 1 && (
              <Div
                sx={{
                  fontSize: '1.3rem',
                  fontWeight: 700,
                  pb: 2,
                  color: 'text.secondary',
                }}
              >
                :
              </Div>
            )}
          </Flex>
        ))}

        {meridiem && (
          <Flex aCenter gap={1} ml={0.5}>
            <ToggleSpinner
              text={meridiem.value.toUpperCase()}
              label={meridiem.label}
              onToggle={meridiem.onToggle}
            />
          </Flex>
        )}
      </Flex>
    </Div>
  );
}

function NumberSpinner({
  max,
  text,
  parse,
  focused,
  label,
  onStep,
  onSet,
  onFocus,
  onBlur,
}: {
  max: number;
  text: string;
  parse?: (typed: number) => number;
  focused?: boolean;
  label: string;
  onStep: (dir: 1 | -1) => void;
  onSet: (modelValue: number) => void;
  onFocus: () => void;
  onBlur: () => void;
}) {
  const inputRef = useRef<HTMLInputElement>(null);
  // `editing` decouples the input from the model while typing so a partial
  // entry ("1") isn't clobbered by the padded model value ("01"). Stepping
  // exits editing so it shows the fresh model value instead of a stale draft.
  const [editing, setEditing] = useState(false);
  const [draft, setDraft] = useState('');

  const typedMax = parse ? 12 : max - 1;

  const step = (dir: 1 | -1) => {
    inputRef.current?.focus();
    setEditing(false);
    onStep(dir);
  };

  useEffect(() => {
    const el = inputRef.current;
    if (!el) {
      return;
    }
    const handler = (e: globalThis.WheelEvent) => {
      if (!focused) {
        return;
      }
      e.preventDefault();
      setEditing(false);
      onStep(e.deltaY < 0 ? 1 : -1);
    };
    el.addEventListener('wheel', handler, { passive: false });
    return () => el.removeEventListener('wheel', handler);
  }, [focused, onStep]);

  const handleFocus = () => {
    setEditing(true);
    setDraft(text);
    onFocus();
  };

  const handleBlur = () => {
    setEditing(false);
    onBlur();
  };

  const handleChange = (e: ChangeEvent<HTMLInputElement>) => {
    setEditing(true);
    const digits = e.target.value.replace(/\D/g, '').slice(-2);
    if (digits === '') {
      setDraft('');
      return;
    }
    const n = parseInt(digits, 10);
    if (n > typedMax) {
      return;
    }
    setDraft(digits);
    onSet(parse ? parse(n) : n);
  };

  const shown = editing ? draft : text;

  return (
    <Flex column aCenter gap={0.75}>
      <IconButton
        sx={stepBtnSx}
        onMouseDown={(e) => e.preventDefault()}
        onClick={() => step(1)}
      >
        <LuChevronUp />
      </IconButton>

      <Box
        component="input"
        ref={inputRef}
        inputMode="numeric"
        maxLength={2}
        value={shown}
        onFocus={handleFocus}
        onChange={handleChange}
        onBlur={handleBlur}
        onKeyDown={(e: KeyboardEvent<HTMLInputElement>) => {
          if (e.key === 'ArrowUp') {
            e.preventDefault();
            step(1);
          } else if (e.key === 'ArrowDown') {
            e.preventDefault();
            step(-1);
          } else if (e.key === 'Enter') {
            e.currentTarget.blur();
          }
        }}
        sx={{
          ...boxSx,
          borderColor: focused ? 'primary.main' : 'divider',
          textAlign: 'center',
          p: 0,
          bgcolor: 'transparent',
          color: 'text.primary',
          fontFamily: 'inherit',
          '&::-webkit-outer-spin-button, &::-webkit-inner-spin-button': {
            WebkitAppearance: 'none',
            margin: 0,
          },
        }}
      />

      <IconButton
        sx={stepBtnSx}
        onMouseDown={(e) => e.preventDefault()}
        onClick={() => step(-1)}
      >
        <LuChevronDown />
      </IconButton>

      <UnitLabel>{label}</UnitLabel>
    </Flex>
  );
}

function ToggleSpinner({
  text,
  label,
  onToggle,
}: {
  text: string;
  label: string;
  onToggle: () => void;
}) {
  const boxRef = useRef<HTMLDivElement>(null);
  const [focused, setFocused] = useState(false);
  const toggle = () => {
    boxRef.current?.focus();
    onToggle();
  };
  return (
    <Flex column aCenter gap={0.75}>
      <IconButton sx={stepBtnSx} onClick={toggle}>
        <LuChevronUp />
      </IconButton>

      <Flex
        ref={boxRef}
        center
        tabIndex={0}
        onFocus={() => setFocused(true)}
        onBlur={() => setFocused(false)}
        onKeyDown={(e) => {
          if (e.key === 'ArrowUp' || e.key === 'ArrowDown') {
            e.preventDefault();
            onToggle();
          }
        }}
        sx={{ ...boxSx, borderColor: focused ? 'primary.main' : 'divider' }}
      >
        {text}
      </Flex>

      <IconButton sx={stepBtnSx} onClick={toggle}>
        <LuChevronDown />
      </IconButton>

      <UnitLabel>{label}</UnitLabel>
    </Flex>
  );
}

function UnitLabel({ children }: { children: ReactNode }) {
  return (
    <Div
      sx={{
        fontSize: '0.65rem',
        fontWeight: 700,
        letterSpacing: '0.04em',
        textTransform: 'uppercase',
        color: 'text.secondary',
      }}
    >
      {children}
    </Div>
  );
}
