import { createContext } from 'react';

import type { ContextSetStateAction, Noop } from '@salvon/types';

export type MenuControlContextProps = {
  mobileOpen: boolean;
  setMobileOpen: ContextSetStateAction<boolean>;
  toggleMobileOpened: Noop;
  hideMobile: Noop;
  showMobile: Noop;

  compactMode: boolean;
  setCompactMode: ContextSetStateAction<boolean>;
  toggleCompactMode: Noop;
  enableCompactMode: Noop;
  disableCompactMode: Noop;
};

const Context = createContext<MenuControlContextProps>({
  mobileOpen: false,
  setMobileOpen: () => undefined,
  toggleMobileOpened: () => undefined,
  hideMobile: () => undefined,
  showMobile: () => undefined,

  compactMode: false,
  setCompactMode: () => undefined,
  toggleCompactMode: () => undefined,
  enableCompactMode: () => undefined,
  disableCompactMode: () => undefined,
});

export default Context;
