import { Box, type BoxProps } from '@mui/material';

import type { CSSProperties } from 'react';

import { voc } from '@salvon/utils/object';

export type DivProps = BoxProps & {
  fw?: boolean;
  fh?: boolean;
  m?: CSSProperties['margin'];
  mt?: CSSProperties['marginTop'];
  mb?: CSSProperties['marginBottom'];
  mr?: CSSProperties['marginRight'];
  ml?: CSSProperties['marginLeft'];
  p?: CSSProperties['padding'];
  pt?: CSSProperties['paddingTop'];
  pb?: CSSProperties['paddingBottom'];
  pr?: CSSProperties['paddingRight'];
  pl?: CSSProperties['paddingLeft'];
};

export default function Div({
  sx,
  fw,
  fh,
  m,
  mt,
  mb,
  mr,
  ml,
  p,
  pt,
  pb,
  pr,
  pl,
  ...props
}: DivProps) {
  sx = {
    ...voc(!!fw, { width: '100%' }, {}),
    ...voc(!!fh, { height: '100%' }, {}),
    margin: m,
    marginTop: mt,
    marginBottom: mb,
    marginRight: mr,
    marginLeft: ml,
    padding: p,
    paddingTop: pt,
    paddingBottom: pb,
    paddingRight: pr,
    paddingLeft: pl,
    ...sx,
  };

  return <Box {...props} sx={sx} />;
}
