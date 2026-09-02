import { createContext } from 'react';

import { themeMode } from '@salvon/consts/theme-mode';
import type { ContextSetStateAction, Noop } from '@salvon/types';

export type ThemeContextProps = {
  theme: string;
  setTheme: ContextSetStateAction<string>;
  handleToggleTheme: Noop;
};

export const Context = createContext<ThemeContextProps>({
  theme: themeMode.light,
  setTheme: () => {},
  handleToggleTheme: () => {},
});

export default Context;
