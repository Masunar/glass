import { TextField } from '@mui/material';

import {
  type CSSProperties,
  type ClipboardEvent,
  type ForwardedRef,
  type InputEvent,
  type KeyboardEvent,
  useEffect,
  useImperativeHandle,
  useRef,
  useState,
} from 'react';

import { Flex } from '@salvon/components/div';

export type PinPadProps = {
  gap?: CSSProperties['gap'];
  width?: CSSProperties['width'];
  height?: CSSProperties['height'];
  length?: number;
  defaultValue?: string[];
  onChange?: (strValue: string, value: string[]) => void;
  onPaste?: (strValue: string, value: string[]) => void;
  onLastInputChange?: (strValue: string, value: string[]) => void;
  autofocus?: boolean;
  fullWidth?: boolean;
  ref?: ForwardedRef<PinPadRef>;
};

export type PinPadRef = {
  onPaste: (e: ClipboardEvent<any>) => void;
};

export default function PinPad({
  onChange,
  onPaste,
  onLastInputChange,
  gap = 1,
  length = 6,
  width = '50px',
  height = '45px',
  autofocus = true,
  fullWidth = false,
  defaultValue = [],
  ref,
}: PinPadProps) {
  const inputRef = useRef<any[]>([]);
  const [values, setValues] = useState([
    ...defaultValue,
    ...Array.fromLength(length - defaultValue.length, ''),
  ]);

  useEffect(() => {
    if (onChange) {
      onChange(values.join(''), values);
    }
  }, [values]);

  useImperativeHandle(ref, () => ({
    onPaste: handlePaste,
  }));

  const handleChange = (e: any, position: number) => {
    const rawValue = e.target.value.toUpperCase();
    const value: string = e.target.value.toUpperCase().substring(0, 1);
    if (value === ' ') {
      return;
    }

    const nv = [...values];

    nv[position] = value;

    if (onLastInputChange && position === length - 1) {
      onLastInputChange(nv.join(''), nv);
    }

    if (value.length > 0 && position + 1 < length) {
      inputRef.current[position + 1].select();
    }

    if (rawValue.length > 1 && position === length - 1) {
      nv[position] = rawValue.substring(1, 2);
    }

    setValues(nv);
  };

  const handleKeyboardActions = (
    e: KeyboardEvent<HTMLInputElement>,
    position: number,
  ) => {
    const target = e.target as any;

    if (e.key == 'a' && e.metaKey) {
      e.preventDefault();
      inputRef.current[0].select();
    }

    if (e.key === 'Backspace' && position - 1 >= 0) {
      e.preventDefault();

      setValues((next) => {
        const nv = [...next];
        nv[position] = '';

        return nv;
      });

      if (!target.value || position !== length - 1) {
        inputRef.current[position - 1].select();
      }
    }

    if (e.key === 'ArrowLeft') {
      e.preventDefault();

      const nextPosition = position - 1 >= 0 ? position - 1 : 0;
      inputRef.current[nextPosition].select();
      return;
    }

    if (e.key === 'ArrowRight') {
      e.preventDefault();

      const nextPosition = position + 1 < length ? position + 1 : length - 1;
      inputRef.current[nextPosition].select();
      return;
    }
  };

  const handlePaste = (e: ClipboardEvent<any>) => {
    e.preventDefault();
    const val = e.clipboardData
      .getData('text')
      .trim()
      .toUpperCase()
      .replaceAll(' ', '')
      .substring(0, length)
      .split('');

    const stayAt = val.length - 1;
    const lastElement = length - 1;
    const elementIndex = stayAt === lastElement ? lastElement : stayAt + 1;

    while (val.length < length) {
      val.push('');
    }

    setValues(val);
    inputRef.current[elementIndex].select();

    if (onPaste) {
      onPaste(val.join(''), val);
    }
  };

  return (
    <Flex gap={gap} onPaste={handlePaste} wrap fw={fullWidth}>
      {values.map((v, i) => (
        <TextField
          key={i}
          value={v}
          autoFocus={autofocus && i === 0}
          sx={fullWidth ? { flex: 1 } : undefined}
          ref={(component) => {
            inputRef.current[i] = component?.querySelector('input') ?? null;
          }}
          onKeyDown={(e) =>
            handleKeyboardActions(e as KeyboardEvent<HTMLInputElement>, i)
          }
          onInput={(e) => handleChange(e as InputEvent<HTMLInputElement>, i)}
          onClick={(e: any) => e.target.select()}
          slotProps={{
            input: {
              sx: {
                height,
                width: fullWidth ? '100%' : width,
              },
              autoComplete: 'off',
            },
            htmlInput: {
              sx: {
                textAlign: 'center',
              },
            },
          }}
        />
      ))}
    </Flex>
  );
}
