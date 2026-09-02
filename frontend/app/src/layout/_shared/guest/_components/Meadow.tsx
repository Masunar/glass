import { Box } from '@mui/material';

import type { ReactNode } from 'react';

/* grass band at the bottom of the scene, with a hill rolling over its lower half */
export default function Meadow({
  children,
  height = '38%',
}: {
  children: ReactNode;
  height?: string;
}) {
  return (
    <Box
      sx={{
        position: 'absolute',
        bottom: 0,
        left: 0,
        right: 0,
        height,
        background: '#cadeb4',
      }}
    >
      <Box
        sx={{
          position: 'absolute',
          top: '35%',
          left: '-15%',
          width: '130%',
          height: '120%',
          borderRadius: '50% 50% 0 0',
          background: '#bcd6a1',
        }}
      />
      {children}
    </Box>
  );
}
