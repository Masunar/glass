import {
  type LinkProps as MUILinkProps,
  Link as MuiLink,
  type SxProps,
} from '@mui/material';

import type { ReactNode } from 'react';
import { Link as RouterLink } from 'react-router';

import generatePath, {
  type GeneratePathParams,
  type GeneratePathUrl,
} from '@salvon/utils/generate-path';
import { isObject } from '@salvon/utils/type-check';

export type LinkProps = {
  children: ReactNode;
  sx?: SxProps;
  path?: GeneratePathUrl;
  href?: GeneratePathUrl;
  pathParams?: GeneratePathParams;
  target?: string;
  disableDecoration?: boolean;
} & Omit<MUILinkProps, 'component' | 'href'>;

export default function Link({
  children,
  sx,
  target,
  path,
  href,
  pathParams = {},
  disableDecoration = true,
  ...props
}: LinkProps) {
  path = isObject(path) ? (path.path ?? '/') : path;
  href = isObject(href) ? (href.path ?? '/') : href;

  return (
    <MuiLink
      {...props}
      component={RouterLink}
      sx={{ ...(disableDecoration && { textDecoration: 'none' }), ...sx }}
      to={generatePath(path || href || '', pathParams)}
      target={target}
    >
      {children}
    </MuiLink>
  );
}
