import { Paper, type PaperProps } from '@mui/material';

import CardHeading, { type CardHeadingProps } from './CardHeading';
import { type ReactNode } from 'react';

import { Div, type DivProps } from '@salvon/components/div';
import { usePalette } from '@salvon/hooks/useTheme';
import type { SlotItem } from '@salvon/types';
import { voc } from '@salvon/utils/object';

export type CardProps = {
  padding?: number | string;
  blank?: boolean;
  children?: ReactNode;
  fw?: boolean;
  elevation?: number;
  heading?: CardHeadingProps;
  /** Only applied when `heading` is set. */
  slotProps?: {
    header?: SlotItem<DivProps>;
    body?: SlotItem<DivProps>;
  };
} & PaperProps;

export default function Card({
  blank,
  children,
  fw,
  elevation,
  sx,
  padding = 2.2,
  heading,
  slotProps,
  ...props
}: CardProps) {
  const palette = usePalette();
  const { header, body: bodySlot } = slotProps ?? {};

  const body = heading ? (
    <Div>
      <Div {...header} sx={{ padding, ...(header?.sx ?? {}) }}>
        <CardHeading {...heading} />
      </Div>
      {children && (
        <Div
          {...bodySlot}
          sx={{
            padding,
            borderTop: `1px solid ${palette.mode === 'dark' ? '#303030' : '#dbe1ea'}`,
            ...bodySlot?.sx,
          }}
        >
          {children}
        </Div>
      )}
    </Div>
  ) : (
    children
  );

  return (
    <Paper
      sx={{
        ...(palette?.salvon?.card ?? {}),
        ...voc(!!padding && !heading, { padding: padding }),
        ...voc(!!heading, { overflow: 'hidden' }),
        ...sx,
        ...voc(!!blank, { backgroundColor: 'transparent' }),
        ...voc(!!fw, { width: '100%' }),
      }}
      elevation={blank ? 0 : elevation}
      {...props}
    >
      {body}
    </Paper>
  );
}
