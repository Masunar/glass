import { Typography } from '@mui/material';

type Props = {
  children: string;
};

export default function GroupLabel({ children }: Props) {
  return (
    <Typography
      sx={{
        fontSize: 11,
        fontWeight: 700,
        letterSpacing: '0.08em',
        textTransform: 'uppercase',
        color: 'text.secondary',
      }}
    >
      {children}
    </Typography>
  );
}
