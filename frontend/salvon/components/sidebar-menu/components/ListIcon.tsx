import { ListItemIcon, type ListItemIconProps } from '@mui/material';

import { usePalette } from '@salvon/hooks/useTheme';

export type ListIconProps = ListItemIconProps;

export default function ListIcon({ sx, ...props }: ListIconProps) {
  const palette = usePalette();

  return (
    <ListItemIcon
      sx={{
        ...((palette.salvon?.menu?.list_icon ?? {}) as any),
        ...sx,
      }}
      {...props}
    />
  );
}
