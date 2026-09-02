import { ListSubheader, type ListSubheaderProps } from '@mui/material';

import { usePalette } from '@salvon/hooks/useTheme';

export type HeaderProps = ListSubheaderProps & {
  compactMode: boolean;
};

export default function Header({
  children,
  compactMode,
  sx,
  ...props
}: HeaderProps) {
  const palette = usePalette();

  return (
    <ListSubheader
      sx={{
        boxSizing: 'border-box',
        listStyle: 'none',
        fontWeight: 500,
        lineHeight: 1,
        textTransform: 'capitalize',
        background: 'inherit',
        position: 'relative',
        ...sx,
        ...((palette.salvon?.menu?.header ?? {}) as any),
        padding: compactMode ? '24px 0px 4px' : '24px 6px 8px',
        fontSize: compactMode ? '0.725rem' : '0.82rem',
        textAlign: compactMode ? 'center' : 'left',
      }}
      {...props}
    >
      {children}
    </ListSubheader>
  );
}
