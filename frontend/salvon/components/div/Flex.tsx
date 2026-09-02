import type { CSSProperties } from 'react';

import { Div, type DivProps } from '@salvon/components/div';
import { voc } from '@salvon/utils/object';

export type FlexProps = {
  jCenter?: boolean;
  jEnd?: boolean;
  jBetween?: boolean;
  jAround?: boolean;

  aCenter?: boolean;
  aEnd?: boolean;
  aStretch?: boolean;

  center?: boolean;
  justify?: CSSProperties['justifyContent'];
  align?: CSSProperties['alignItems'];
  gap?: CSSProperties['gap'];

  column?: boolean;
  fw?: boolean;
  fh?: boolean;
  wrap?: boolean;
} & Omit<DivProps, 'justifyContent' | 'alignItems'>;

export default function Flex({
  jCenter,
  jEnd,
  jBetween,
  jAround,

  aCenter,
  aEnd,
  aStretch,

  center,
  justify,
  align,
  gap,

  column,
  fw,
  fh,
  wrap,
  sx,

  children,
  ...props
}: FlexProps) {
  let jContent = undefined;
  let aItems = undefined;

  if (jCenter) {
    jContent = 'center';
  } else if (jBetween) {
    jContent = 'space-between';
  } else if (jEnd) {
    jContent = 'end';
  } else if (jAround) {
    jContent = 'space-around';
  }

  if (aCenter) {
    aItems = 'center';
  } else if (aEnd) {
    aItems = 'end';
  } else if (aStretch) {
    aItems = 'stretch';
  }

  if (center) {
    jContent = 'center';
    aItems = 'center';
  }

  if (justify) {
    jContent = justify;
  }

  if (align) {
    aItems = align;
  }

  sx = {
    display: 'flex',
    ...voc(!!jContent, { justifyContent: jContent }, {}),
    ...voc(!!aItems, { alignItems: aItems }, {}),
    ...voc(!!wrap, { flexWrap: 'wrap' }, {}),
    ...voc(!!column, { flexDirection: 'column' }, {}),
    ...voc(!!fw, { width: '100%' }, {}),
    ...voc(!!fh, { height: '100%' }, {}),
    gap,
    ...sx,
  };

  return (
    <Div {...props} sx={sx}>
      {children}
    </Div>
  );
}
