import { Chip, type ChipProps } from '@mui/material';

export type KbdProps = ChipProps;

export default function Kbd({ label, ...props }: KbdProps) {
  const { sx } = props ?? {};

  return (
    <Chip
      {...props}
      sx={{
        borderRadius: '5px',
        background: 'transparent',
        border: '1px solid transparent',
        cursor: 'pointer',
        height: '28px',
        '& .MuiChip-label': {
          padding: '6px 8px',
        },
        ...sx,
      }}
      label={<span style={{ wordSpacing: '1px' }}>{label}</span>}
    />
  );
}
