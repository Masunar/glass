import Kbd from './Kbd';
import { type KeyboardEvent, type ReactNode, useEffect, useRef } from 'react';
import { IoSearchOutline } from 'react-icons/io5';

import { Flex } from '@salvon/components/div';
import { useIsDarkMode, usePalette } from '@salvon/hooks/useTheme';

export type InputProps = {
  value: string;
  onChange: (v: string) => void;
  onKeyDown: (e: KeyboardEvent<HTMLInputElement>) => void;
  onClose: () => void;
  icon?: ReactNode;
  placeholder?: string;
};

export default function Input({
  value,
  onChange,
  onKeyDown,
  onClose,
  icon,
  placeholder,
}: InputProps) {
  const palette = usePalette();
  const dark = useIsDarkMode();
  const cfg = palette.salvon?.omni_search ?? {};
  const muted = palette.text?.secondary ?? (dark ? '#8a8f98' : '#94a3b8');
  const ref = useRef<HTMLInputElement>(null);

  useEffect(() => ref.current?.focus(), []);

  return (
    <Flex
      aCenter
      gap={1.25}
      sx={{
        px: 2,
        height: 56,
        borderBottom: `1px solid ${cfg.border ?? (dark ? '#2c2c2f' : '#eef0f3')}`,
      }}
    >
      {icon ?? <IoSearchOutline size={20} color={muted} />}
      <input
        ref={ref}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        onKeyDown={onKeyDown}
        autoFocus
        placeholder={placeholder ?? 'Search...'}
        style={{
          flex: 1,
          border: 'none',
          background: 'transparent',
          fontSize: '1.05rem',
          outline: 'none',
          color: palette.text?.primary,
        }}
      />
      <Kbd onClick={onClose} sx={{ cursor: 'pointer', color: muted }}>
        Esc
      </Kbd>
    </Flex>
  );
}
