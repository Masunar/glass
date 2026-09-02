import { useMediaQuery } from '@mui/material';
import type { Breakpoint } from '@mui/material';

import { useBreakpoints } from '@salvon/hooks/useTheme';

export const useIsOver = (breakpoint: Breakpoint) => {
  const breakpoints = useBreakpoints();
  return useMediaQuery(breakpoints.up(breakpoint));
};

export const useIsUnder = (breakpoint: Breakpoint) => {
  const breakpoints = useBreakpoints();
  return useMediaQuery(breakpoints.down(breakpoint));
};

export const useIsOnly = (breakpoint: Breakpoint) => {
  const breakpoints = useBreakpoints();
  return useMediaQuery(breakpoints.only(breakpoint));
};

export const useIsNot = (breakpoint: Breakpoint) => {
  const breakpoints = useBreakpoints();
  return useMediaQuery(breakpoints.not(breakpoint));
};

export const useIsBetween = (start: Breakpoint, end: Breakpoint) => {
  const breakpoints = useBreakpoints();
  return useMediaQuery(breakpoints.between(start, end));
};
