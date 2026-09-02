import { ListItemButton, type ListItemButtonProps } from '@mui/material';

import { usePalette } from '@salvon/hooks/useTheme';

export type ListButtonProps = ListItemButtonProps & {
  padding?: number | string;
};

export default function ListButton({ padding, sx, ...props }: ListButtonProps) {
  const palette = usePalette();

  return (
    <ListItemButton
      sx={{
        padding: padding ?? '6px 11px 6px 20px',
        borderRadius: '4px',
        fontSize: '0.85rem',
        fontWeight: 500,
        overflow: 'hidden',
        textOverflow: 'ellipsis',
        whiteSpace: 'nowrap',
        ...((palette.salvon?.menu?.list_button ?? {}) as any),
        ...sx,
      }}
      {...props}
    />
  );
}
