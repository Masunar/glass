import { Box } from '@mui/material';

import type { ReactNode } from 'react';

type Props = {
  banner?: ReactNode; // shown on the towed banner; without it the plane flies bare
  top?: string;
  duration?: number; // seconds for one pass across the sky
};

export default function Plane({ banner, top = '14%', duration = 30 }: Props) {
  return (
    <Box
      sx={{
        position: 'absolute',
        top,
        display: 'flex',
        alignItems: 'center',
        gap: '6px',
        whiteSpace: 'nowrap',
        animation: `plane-fly ${duration}s linear infinite`,
        '@keyframes plane-fly': {
          from: { left: '-45%' },
          to: { left: '115%' },
        },
      }}
    >
      {banner && (
        <Box
          sx={{
            padding: '7px 12px',
            background: '#fff',
            color: '#5b6b82',
            fontSize: 12,
            fontWeight: 700,
            letterSpacing: '.08em',
            transformOrigin: 'right center',
            // rippling edges + a slight flutter, so the cloth reads as waving
            animation:
              'banner-ripple 1.4s ease-in-out infinite alternate, banner-flutter 2.6s ease-in-out infinite alternate',
            '@keyframes banner-ripple': {
              from: {
                clipPath:
                  'polygon(0 8%, 12% 0, 25% 8%, 38% 16%, 50% 8%, 62% 0, 75% 8%, 88% 16%, 100% 8%, 100% 92%, 88% 100%, 75% 92%, 62% 84%, 50% 92%, 38% 100%, 25% 92%, 12% 84%, 0 92%)',
              },
              to: {
                clipPath:
                  'polygon(0 16%, 12% 8%, 25% 0, 38% 8%, 50% 16%, 62% 8%, 75% 0, 88% 8%, 100% 16%, 100% 100%, 88% 92%, 75% 84%, 62% 92%, 50% 100%, 38% 92%, 25% 84%, 12% 92%, 0 100%)',
              },
            },
            '@keyframes banner-flutter': {
              from: { transform: 'rotate(-2deg) skewY(1.5deg)' },
              to: { transform: 'rotate(2deg) skewY(-1.5deg)' },
            },
          }}
        >
          {banner}
        </Box>
      )}
      {/* tow rope */}
      {banner && <Box sx={{ width: 10, height: 2, background: '#c3ccda' }} />}

      {/* side view: tail fin, straight fuselage with a pointed nose, swept wing */}
      <Box sx={{ position: 'relative', width: 38, height: 16 }}>
        <Box
          sx={{
            position: 'absolute',
            top: 0,
            left: 1,
            width: 10,
            height: 8,
            background: '#adbfd6',
            clipPath: 'polygon(0 0, 100% 100%, 0 100%)',
          }}
        />
        <Box
          sx={{
            position: 'absolute',
            top: 6,
            left: 0,
            width: 38,
            height: 7,
            borderRadius: '2px',
            background: '#fff',
            clipPath: 'polygon(0 0, 82% 0, 100% 50%, 82% 100%, 0 100%)',
          }}
        />
        <Box
          sx={{
            position: 'absolute',
            top: 11,
            left: 12,
            width: 15,
            height: 5,
            background: '#adbfd6',
            clipPath: 'polygon(28% 0, 100% 0, 72% 100%, 0 100%)',
          }}
        />
        {/* cockpit */}
        <Box
          sx={{
            position: 'absolute',
            top: 7,
            right: 7,
            width: 4,
            height: 3,
            borderRadius: '1px',
            background: '#9fb2c9',
          }}
        />
      </Box>
    </Box>
  );
}
