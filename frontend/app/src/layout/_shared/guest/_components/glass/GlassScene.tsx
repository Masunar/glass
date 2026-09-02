import { Box } from '@mui/material';

import GlassPane from './GlassPane';
import type { ReactNode } from 'react';

/**
 * Panel dekoracyjny ekranów dla niezalogowanych — tafle szkła
 * z odbiciem światła przechodzącym po scenie.
 *
 * Całość jest zbudowana z CSS-a, bez plików graficznych, więc nie
 * dokłada nic do transferu i skaluje się bez utraty ostrości.
 */
export default function GlassScene({ brand }: { brand?: ReactNode }) {
  return (
    <Box
      sx={{
        position: 'relative',
        height: '100%',
        minHeight: '100%',
        overflow: 'hidden',
        borderRadius: 1,
        background:
          'radial-gradient(120% 85% at 76% 6%, rgba(255,255,255,.92), rgba(255,255,255,0) 52%),' +
          'linear-gradient(158deg, #d8e4f0 0%, #eaeff5 48%, #dfeaec 100%)',
      }}
    >
      <GlassPane
        width={250}
        height={340}
        top="14%"
        left="16%"
        rotate={-7}
        edge={0.38}
        duration={18}
      />
      <GlassPane
        width={190}
        height={260}
        top="34%"
        left="38%"
        rotate={5}
        edge={0.3}
        delay={1.5}
        duration={15}
      />
      <GlassPane
        width={140}
        height={190}
        top="20%"
        right="16%"
        rotate={-3}
        edge={0.26}
        delay={0.8}
        duration={20}
        opacity={0.9}
      />
      <GlassPane
        width={110}
        height={148}
        bottom="20%"
        right="26%"
        rotate={9}
        edge={0.34}
        delay={2.4}
        duration={17}
        opacity={0.85}
      />
      <GlassPane
        width={86}
        height={116}
        bottom="14%"
        left="12%"
        rotate={-12}
        edge={0.24}
        delay={3.1}
        duration={19}
        opacity={0.72}
      />

      {/* odbicie przechodzące po całej scenie */}
      <Box
        aria-hidden
        sx={{
          position: 'absolute',
          inset: '-25% -45%',
          pointerEvents: 'none',
          mixBlendMode: 'soft-light',
          background:
            'linear-gradient(104deg, transparent 40%, rgba(255,255,255,.9) 50%, transparent 60%)',
          animation: 'lightSweep 14s ease-in-out infinite',
          '@keyframes lightSweep': {
            '0%': { transform: 'translateX(-34%)', opacity: 0 },
            '18%': { opacity: 1 },
            '62%': { opacity: 1 },
            '80%, 100%': { transform: 'translateX(34%)', opacity: 0 },
          },
          '@media (prefers-reduced-motion: reduce)': {
            animation: 'none',
            opacity: 0.35,
          },
        }}
      />

      {brand && (
        <Box
          sx={{
            position: 'absolute',
            left: 44,
            bottom: 40,
            zIndex: 1,
          }}
        >
          {brand}
        </Box>
      )}
    </Box>
  );
}
