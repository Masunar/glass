import type { ReactNode } from 'react';

import { Div } from '@salvon/components/div';
import { useIsDarkMode, usePalette } from '@salvon/hooks/useTheme';

export default function Empty({ children }: { children: ReactNode }) {
  const palette = usePalette();
  const dark = useIsDarkMode();
  const muted = palette.text?.secondary ?? (dark ? '#8a8f98' : '#94a3b8');

  return (
    <Div
      sx={{
        px: 1.5,
        py: 3,
        textAlign: 'center',
        fontSize: '0.9rem',
        color: muted,
      }}
    >
      {children}
    </Div>
  );
}
