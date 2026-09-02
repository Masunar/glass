import { AppBar } from '@mui/material';

import TopbarSizer from './TopbarSizer';
import type { ReactNode } from 'react';

import { Div } from '@salvon/components/div';
import { usePalette } from '@salvon/hooks/useTheme';

export type TopbarProps = {
  drawerWidth: number;
  padding?: number | string;
  height?: number | string;
  children: ReactNode;
};

export default function Topbar({
  drawerWidth,
  padding,
  children,
  height,
}: TopbarProps) {
  const palette = usePalette();

  return (
    <Div style={{ height: height }}>
      <AppBar
        elevation={0}
        sx={{
          background: '#fff',
          width: { md: '100%' },
          backdropFilter: 'blur(10px)',
          transition: 'none !important',
          ...(palette.salvon?.topbar ?? {}),
        }}
      >
        <Div
          sx={{
            pl: { md: `${drawerWidth}px` },
          }}
        >
          <Div
            sx={{
              padding: padding ?? '0 25px',
            }}
          >
            <TopbarSizer height={height}>{children}</TopbarSizer>
          </Div>
        </Div>
      </AppBar>
    </Div>
  );
}
