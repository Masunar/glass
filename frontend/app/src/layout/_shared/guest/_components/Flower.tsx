import { Box } from '@mui/material';

import type { Pos } from './position';

type Props = Pos & {
  color: string;
  size?: number;
};

/* colored head + a dot of a stem */
export default function Flower({ color, size = 12, ...pos }: Props) {
  return (
    <Box sx={{ position: 'absolute', ...pos }}>
      <Box
        sx={{
          width: size,
          height: size,
          borderRadius: '50%',
          background: color,
        }}
      />
      <Box
        sx={{
          width: size / 3,
          height: size / 3,
          borderRadius: '50%',
          background: '#a9c78e',
          margin: `${size / 3}px auto 0`,
        }}
      />
    </Box>
  );
}
