import { Box } from '@mui/material';

import type { Pos } from './position';
import type { ReactNode } from 'react';

type Props = Pos & {
  width?: string;
  height?: string;
  children?: ReactNode;
};

const water = '#a6cde4';
const shallow = '#c2ddee';

/* irregular pond: a few overlapping ellipses instead of one clean oval */
export default function Pond({
  width = '34%',
  height = '13%',
  children,
  ...pos
}: Props) {
  return (
    <Box sx={{ position: 'absolute', width, height, ...pos }}>
      {/* damp shore, slightly bigger than the water itself */}
      <Box
        sx={{
          position: 'absolute',
          inset: '-8% -4%',
          borderRadius: '50%',
          background: '#b7d3a5',
        }}
      />
      <Box
        sx={{
          position: 'absolute',
          inset: 0,
          borderRadius: '50%',
          background: water,
        }}
      />
      {/* bulges that break up the oval */}
      <Box
        sx={{
          position: 'absolute',
          left: '-8%',
          top: '18%',
          width: '46%',
          height: '78%',
          borderRadius: '50%',
          background: water,
        }}
      />
      <Box
        sx={{
          position: 'absolute',
          right: '-6%',
          top: '10%',
          width: '38%',
          height: '70%',
          borderRadius: '50%',
          background: water,
        }}
      />
      {/* shallow water catching the light */}
      <Box
        sx={{
          position: 'absolute',
          left: '14%',
          top: '46%',
          width: '54%',
          height: '38%',
          borderRadius: '50%',
          background: shallow,
          opacity: 0.7,
        }}
      />
      {[
        { top: '30%', left: '20%', width: '22%' },
        { top: '58%', left: '48%', width: '16%' },
        { top: '44%', left: '68%', width: '12%' },
      ].map((ripple) => (
        <Box
          key={ripple.left}
          sx={{
            position: 'absolute',
            height: 2,
            borderRadius: 2,
            background: 'rgba(255,255,255,.65)',
            ...ripple,
          }}
        />
      ))}
      {children}
    </Box>
  );
}
