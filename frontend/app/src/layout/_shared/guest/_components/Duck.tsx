import { Box } from '@mui/material';

import type { Pos } from './position';

type Props = Pos & {
  size?: number; // body width in px
  swim?: string; // how far it paddles to the right before turning around
  duration?: number; // seconds for a full round trip
};

/* little duck paddling back and forth, bobbing on the surface, wake trailing behind it */
export default function Duck({
  size = 24,
  swim = '60px',
  duration = 12,
  ...pos
}: Props) {
  const body = size;
  const head = size * 0.42;

  return (
    <Box
      sx={{
        position: 'absolute',
        width: body,
        height: body * 0.9,
        animation: `duck-swim ${duration}s ease-in-out infinite`,
        '@keyframes duck-swim': {
          '0%': { transform: 'translateX(0) scaleX(1)' },
          // pauses at each end and flips in one frame, so it never swims backwards
          '48%': { transform: `translateX(${swim}) scaleX(1)` },
          '49.9%': { transform: `translateX(${swim}) scaleX(1)` },
          '50%': { transform: `translateX(${swim}) scaleX(-1)` },
          '98%': { transform: 'translateX(0) scaleX(-1)' },
          '99.9%': { transform: 'translateX(0) scaleX(-1)' },
          '100%': { transform: 'translateX(0) scaleX(1)' },
        },
        ...pos,
      }}
    >
      <Box
        sx={{
          position: 'absolute',
          inset: 0,
          animation: 'duck-bob 3s ease-in-out infinite',
          '@keyframes duck-bob': {
            '0%, 100%': { transform: 'translateY(0)' },
            '50%': { transform: 'translateY(2px)' },
          },
        }}
      >
        {/* wake */}
        <Box
          sx={{
            position: 'absolute',
            left: -body * 0.5,
            bottom: body * 0.12,
            width: body * 0.7,
            height: 2,
            borderRadius: 2,
            background: 'rgba(255,255,255,.7)',
          }}
        />
        {/* body, tail lifted on the left */}
        <Box
          sx={{
            position: 'absolute',
            bottom: 0,
            left: 0,
            width: body,
            height: body * 0.5,
            borderRadius: '60% 50% 50% 80% / 70% 60% 60% 90%',
            background: '#fff',
          }}
        />
        {/* head */}
        <Box
          sx={{
            position: 'absolute',
            top: 0,
            right: 0,
            width: head,
            height: head,
            borderRadius: '50%',
            background: '#fff',
          }}
        >
          <Box
            sx={{
              position: 'absolute',
              top: '34%',
              left: '30%',
              width: 2,
              height: 2,
              borderRadius: '50%',
              background: '#2d2f30',
            }}
          />
          {/* beak */}
          <Box
            sx={{
              position: 'absolute',
              top: '46%',
              right: -head * 0.42,
              width: head * 0.5,
              height: head * 0.28,
              borderRadius: '20% 60% 60% 20%',
              background: '#f2a03d',
            }}
          />
        </Box>
      </Box>
    </Box>
  );
}
