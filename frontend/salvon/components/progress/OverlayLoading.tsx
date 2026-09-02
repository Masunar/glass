import { type SxProps } from '@mui/material';

import Loading, { type LoadingProps } from './Loading';
import { type ReactNode } from 'react';

import { Div } from '@salvon/components/div';

export type OverlayLoadingProps = {
  loading?: boolean;
  children: ReactNode;
  tip?: ReactNode;
  loaderProps?: LoadingProps;
  sx?: SxProps;
  contentSx?: SxProps;
  overlaySx?: SxProps;
};

export default function OverlayLoading({
  loading = false,
  children,
  tip,
  loaderProps,
  sx,
  contentSx,
  overlaySx,
}: OverlayLoadingProps) {
  return (
    <Div sx={{ position: 'relative', ...sx }}>
      {loading && (
        <Div
          sx={{
            position: 'absolute',
            inset: 0,
            zIndex: 10,
            display: 'flex',
            flexDirection: 'column',
            alignItems: 'center',
            justifyContent: 'center',
            gap: 1,
            background: (theme) =>
              theme.palette.mode === 'dark'
                ? 'rgba(0, 0, 0, 0.45)'
                : 'rgba(255, 255, 255, 0.6)',
            backdropFilter: 'blur(1px)',
            ...overlaySx,
          }}
        >
          <Loading {...loaderProps} />
          {tip}
        </Div>
      )}
      <Div
        sx={{
          pointerEvents: loading ? 'none' : undefined,
          userSelect: loading ? 'none' : undefined,
          ...contentSx,
        }}
      >
        {children}
      </Div>
    </Div>
  );
}
