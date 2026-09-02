import { Box, type BoxProps, useTheme } from '@mui/material';

import type { ForwardedRef } from 'react';

export type ItemProps = BoxProps & {
  activeBg: string;
  activeBorder: string;
  active?: boolean;
  ref?: ForwardedRef<HTMLDivElement>;
};

/** Clickable row wrapper: handles active/hover background and border. */
export default function Item({
  children,
  activeBg,
  activeBorder,
  active,
  sx,
  ref,
  ...props
}: ItemProps) {
  const { palette } = useTheme();

  return (
    <Box
      ref={ref}
      {...props}
      sx={{
        width: '100%',
        userSelect: 'none',
        cursor: 'pointer',
        border: `1px solid ${active ? activeBorder : 'transparent'}`,
        background: active ? activeBg : 'transparent',
        transition: 'background 90ms, border-color 90ms',
        '&:hover': { background: active ? activeBg : palette.action.hover },
        ...sx,
      }}
    >
      {children}
    </Box>
  );
}
