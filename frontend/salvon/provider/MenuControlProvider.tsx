import { type ReactNode, useState } from 'react';

import MenuControlContext from '@salvon/context/MenuControlContext';
import { useGetFromLocalStorage } from '@salvon/hooks/useGetFromLocalStorage';

export type MenuControlProviderType = {
  children: ReactNode;
  compactModeDefault?: boolean;
};

export default function MenuControlProvider({
  children,
  compactModeDefault = false,
}: MenuControlProviderType) {
  const [mobileOpen, setMobileOpen] = useState(false);
  const [compactMode, setCompactMode] = useGetFromLocalStorage<boolean>(
    'compact_menu',
    compactModeDefault ?? false,
  );

  const toggleMobileOpened = () => setMobileOpen(!mobileOpen);
  const hideMobile = () => setMobileOpen(false);
  const showMobile = () => setMobileOpen(true);

  const toggleCompactMode = () => setCompactMode(!compactMode);
  const disableCompactMode = () => setCompactMode(false);
  const enableCompactMode = () => setCompactMode(true);

  return (
    <MenuControlContext.Provider
      value={{
        mobileOpen,
        setMobileOpen,
        toggleMobileOpened,
        hideMobile,
        showMobile,

        compactMode,
        setCompactMode,
        toggleCompactMode,
        disableCompactMode,
        enableCompactMode,
      }}
    >
      {children}
    </MenuControlContext.Provider>
  );
}
