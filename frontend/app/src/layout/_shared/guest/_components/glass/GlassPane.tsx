import { Box } from '@mui/material';

type Props = {
  width: number;
  height: number;
  top?: string;
  left?: string;
  right?: string;
  bottom?: string;
  /** Obrót tafli w stopniach. */
  rotate?: number;
  /**
   * Nasycenie zielonej krawędzi. Szkło float ma zielonkawą krawędź
   * od tlenków żelaza w masie — im grubsza tafla, tym mocniejszą.
   */
  edge?: number;
  /** Przesunięcie fazy unoszenia, w sekundach. */
  delay?: number;
  /** Długość cyklu unoszenia, w sekundach. */
  duration?: number;
  opacity?: number;
};

export default function GlassPane({
  width,
  height,
  top,
  left,
  right,
  bottom,
  rotate = 0,
  edge = 0.32,
  delay = 0,
  duration = 16,
  opacity = 1,
}: Props) {
  return (
    <Box
      aria-hidden
      sx={{
        position: 'absolute',
        top,
        left,
        right,
        bottom,
        width,
        height,
        opacity,
        borderRadius: '3px',
        transform: `rotate(${rotate}deg)`,
        background:
          'linear-gradient(135deg, rgba(255,255,255,.62) 0%, rgba(220,238,240,.32) 44%, rgba(255,255,255,.52) 100%)',
        backdropFilter: 'blur(1.5px)',
        boxShadow: '0 18px 42px -20px rgba(23,48,77,.42)',
        animation: `glassFloat ${duration}s ease-in-out ${delay}s infinite`,
        '@keyframes glassFloat': {
          '0%, 100%': { transform: `rotate(${rotate}deg) translateY(0)` },
          '50%': { transform: `rotate(${rotate}deg) translateY(-9px)` },
        },
        // wąskie odbicie biegnące po przekątnej tafli
        '&::before': {
          content: '""',
          position: 'absolute',
          inset: 0,
          borderRadius: 'inherit',
          pointerEvents: 'none',
          background:
            'linear-gradient(114deg, transparent 32%, rgba(255,255,255,.72) 46%, transparent 60%)',
        },
        // krawędzie: rozświetlona od góry, zielona od dołu
        '&::after': {
          content: '""',
          position: 'absolute',
          inset: 0,
          borderRadius: 'inherit',
          pointerEvents: 'none',
          boxShadow: `inset 1px 1px 0 rgba(255,255,255,.85), inset -1.5px -1.5px 0 rgba(43,110,99,${edge})`,
        },
        '@media (prefers-reduced-motion: reduce)': {
          animation: 'none',
        },
      }}
    />
  );
}
