import { Box } from '@mui/material';

import type { Pos } from './position';

type Props = Pos & {
  size?: number;
};

const rays = [0, 45, 90, 135, 180, 225, 270, 315];

/* core with a soft pulsing glow and slowly turning rays */
export default function Sun({ size = 56, ...pos }: Props) {
  return (
    <Box
      sx={{
        position: 'absolute',
        width: size,
        height: size,
        ...pos,
        '@keyframes sun-turn': {
          to: { transform: 'rotate(360deg)' },
        },
        '@keyframes sun-glow': {
          '0%, 100%': { boxShadow: '0 0 0 8px rgba(247, 208, 96, .18)' },
          '50%': { boxShadow: '0 0 0 16px rgba(247, 208, 96, .10)' },
        },
      }}
    >
      <Box
        sx={{
          position: 'absolute',
          inset: 0,
          animation: 'sun-turn 60s linear infinite',
        }}
      >
        {rays.map((angle) => (
          <Box
            key={angle}
            sx={{
              position: 'absolute',
              top: '50%',
              left: '50%',
              width: 6,
              height: size * 0.42,
              borderRadius: 3,
              background: '#f7d060',
              opacity: 0.75,
              transform: `translate(-50%, -50%) rotate(${angle}deg) translateY(-${size * 0.85}px)`,
            }}
          />
        ))}
      </Box>
      <Box
        sx={{
          position: 'absolute',
          inset: 0,
          borderRadius: '50%',
          background: '#f7d060',
          animation: 'sun-glow 4s ease-in-out infinite',
        }}
      />
    </Box>
  );
}
