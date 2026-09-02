import type { ReactNode } from 'react';
import SimpleBar from 'simplebar-react';
import 'simplebar-react/dist/simplebar.min.css';

import { Div } from '@salvon/components/div';
import { usePalette } from '@salvon/hooks/useTheme';

export type ScrollableContentProps = {
  children: ReactNode;
  width: number;
  padding?: number | string;
  hidden?: boolean;
};

export default function ScrollableContent({
  width,
  padding,
  children,
  hidden,
}: ScrollableContentProps) {
  const palette = usePalette();

  return (
    <SimpleBar
      forceVisible="y"
      style={{
        display: hidden ? 'none' : undefined,
        width: hidden ? 0 : width,
        position: 'fixed',
        height: '100%',
        ...(palette?.salvon?.sidebar ?? {}),
      }}
    >
      <Div
        sx={{
          padding: padding ?? '0 14px',
        }}
      >
        {children}
      </Div>
    </SimpleBar>
  );
}
