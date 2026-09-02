import { Box } from '@mui/material';

import type { Pos } from './position';

type Props = Pos & {
  width?: string;
};

export default function Cloud({ width = '7rem', ...pos }: Props) {
  return (
    <Box
      sx={{
        position: 'absolute',
        width,
        height: '2rem',
        borderRadius: '999px',
        background: '#fff',
        opacity: 0.85,
        boxShadow: '-1.4rem .5rem 0 -.5rem #fff, 1.1rem .7rem 0 -.7rem #fff',
        ...pos,
      }}
    />
  );
}
