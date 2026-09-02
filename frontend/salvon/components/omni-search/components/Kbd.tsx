import { Div, type DivProps } from '@salvon/components/div';
import { useIsDarkMode, usePalette } from '@salvon/hooks/useTheme';

/** Small keyboard-key chip, colored from the `omni_search` theme slice. */
export default function Kbd({ children, sx, ...props }: DivProps) {
  const palette = usePalette();
  const dark = useIsDarkMode();
  const cfg = palette.salvon?.omni_search ?? {};

  return (
    <Div
      sx={{
        px: 0.75,
        py: 0.25,
        fontSize: '0.72rem',
        fontWeight: 600,
        borderRadius: '6px',
        color: palette.text?.primary,
        border: `1px solid ${cfg.chipBorder ?? (dark ? '#2c2c31' : '#e6e9ef')}`,
        background: cfg.chipBg ?? (dark ? '#1c1c1f' : '#f4f6f9'),
        ...sx,
      }}
      {...props}
    >
      {children}
    </Div>
  );
}
