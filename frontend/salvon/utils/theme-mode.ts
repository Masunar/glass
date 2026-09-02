import { themeMode } from '@salvon/consts/theme-mode';

export const isLightMode = (mode: string): boolean => {
  return mode === themeMode.light;
};

export const isDarkMode = (mode: string): boolean => {
  return mode === themeMode.dark;
};
