import { TextField, type TextFieldProps, alpha } from '@mui/material';

import type { ReactNode } from 'react';
import { IoSearchOutline } from 'react-icons/io5';

import { Flex } from '@salvon/components/div';
import { usePalette } from '@salvon/hooks/useTheme';
import type { SlotItem } from '@salvon/types';

export type SearchFieldProps = {
  value: string;
  onChange: (value: string) => void;
  placeholder?: string;
  autoFocus?: boolean;
  icon?: ReactNode | null;
  /** Per-component theme token merged after the base styles. */
  token?: object;
  slotProps?: { field?: SlotItem<TextFieldProps> };
};

/**
 * Neutral search input shared across components (data-hub, settings-center, …).
 * Callers inject a per-component theme token via `token`.
 */
export default function SearchField({
  value,
  onChange,
  placeholder,
  autoFocus,
  icon,
  token,
  slotProps,
}: SearchFieldProps) {
  const palette = usePalette();
  const isDark = palette.mode === 'dark';
  const box = isDark
    ? { border: '#242424', bg: '#141414', hoverBorder: '#2f2f2f' }
    : { border: '#e7ebf1', bg: '#ffffff', hoverBorder: '#cbd5e1' };

  const adornment =
    icon === null ? undefined : (icon ?? <IoSearchOutline size={18} />);

  return (
    <TextField
      fullWidth
      size="small"
      value={value}
      autoFocus={autoFocus}
      onChange={(e) => onChange(e.target.value)}
      placeholder={placeholder}
      slotProps={{
        input: {
          startAdornment: adornment && (
            <Flex aCenter sx={{ mr: 1, color: isDark ? '#6b7280' : '#94a3b8' }}>
              {adornment}
            </Flex>
          ),
        },
      }}
      {...slotProps?.field}
      sx={{
        '& .MuiOutlinedInput-root': {
          borderRadius: '8px',
          background: box.bg,
          transition: 'border-color 150ms, box-shadow 150ms',
          '& fieldset': { borderColor: box.border },
          '&:hover fieldset': { borderColor: box.hoverBorder },
          '&.Mui-focused fieldset': {
            borderColor: 'primary.main',
            borderWidth: '1px',
          },
          '&.Mui-focused': {
            boxShadow: (theme) =>
              `0 0 0 3px ${alpha(theme.palette.primary.main, 0.12)}`,
          },
        },
        ...((token ?? {}) as object),
        ...slotProps?.field?.sx,
      }}
    />
  );
}
