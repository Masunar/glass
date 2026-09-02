import { useTheme as useMuiTheme } from '@mui/material/styles';

import type { Theme } from '@salvon/types';
import { isDarkMode, isLightMode } from '@salvon/utils/theme-mode';

export const useTheme = (): Theme => {
  return useMuiTheme();
};

export const useThemeMode = (): string => {
  const theme = useTheme();

  return theme.palette.mode;
};

export const useIsDarkMode = (): boolean => {
  const currentThemeMode = useThemeMode();

  return isDarkMode(currentThemeMode);
};

export const useIsLightMode = (): boolean => {
  const currentThemeMode = useThemeMode();

  return isLightMode(currentThemeMode);
};

export const usePalette = () => {
  return useTheme().palette ?? {};
};

export const useBreakpoints = () => {
  return useTheme().breakpoints ?? {};
};
