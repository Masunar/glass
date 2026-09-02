import {
  ThemeProvider as MUIThemeProvider,
  type ThemeOptions,
  createTheme,
} from '@mui/material';

import { type ReactNode } from 'react';

import { themeMode } from '@salvon/consts/theme-mode';
import { ThemeModeContext } from '@salvon/context';
import { useGetFromLocalStorage } from '@salvon/hooks/useGetFromLocalStorage';
import type { Theme } from '@salvon/types';
import { getDocument, getWindow } from '@salvon/utils/ssr';
import { isLightMode } from '@salvon/utils/theme-mode';

export type ThemeProviderProps = {
  lightTheme: Theme;
  darkTheme: Theme;
  children: ReactNode;
  defaultMode?: string;
  forceMode?: string;
};

export default function ThemeProvider({
  children,
  lightTheme,
  darkTheme,
  defaultMode,
  forceMode,
}: ThemeProviderProps) {
  const isSystemDarkMode =
    getWindow()?.matchMedia('(prefers-color-scheme: dark)')?.matches ?? false;
  const defaultThemeMode =
    defaultMode ?? (isSystemDarkMode ? themeMode.dark : themeMode.light);

  const [theme, setTheme] = useGetFromLocalStorage<string>(
    'theme_mode',
    defaultThemeMode,
  );

  getDocument()
    ?.querySelector('body')
    ?.setAttribute('data-theme-mode', theme ?? themeMode.light);

  const handleToggleTheme = () => {
    if (isLightMode(theme ?? themeMode.light)) {
      setTheme(themeMode.dark);
      return;
    }

    setTheme(themeMode.light);
  };

  const themeOptions =
    (forceMode ?? isLightMode(theme ?? themeMode.light))
      ? lightTheme
      : darkTheme;

  const muiTheme = createTheme(themeOptions as ThemeOptions);
  return (
    <ThemeModeContext.Provider value={{ theme, setTheme, handleToggleTheme }}>
      <MUIThemeProvider theme={muiTheme}>{children}</MUIThemeProvider>
    </ThemeModeContext.Provider>
  );
}
