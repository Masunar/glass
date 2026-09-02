import {
  SwipeableDrawer as Drawer,
  type SwipeableDrawerProps as DrawerProps,
} from '@mui/material';

import { type ReactNode, useState } from 'react';
import { createContext } from 'react';

import type { Noop } from '@salvon/types';
import { voc } from '@salvon/utils/object';
import { boolVal } from '@salvon/utils/type-transform';

export type DrawerConfig = {
  content: ReactNode;
  onClose?: () => void;
  width?: number | string;
  config?: Partial<
    Omit<
      DrawerProps,
      'open' | 'onOpen' | 'onClose' | 'swipeAreaWidth' | 'children'
    >
  >;
};

export type DrawerContextProps = {
  openDrawer: (config: DrawerConfig) => void;
  closeDrawer: (close: Noop) => void;
};

export const DrawerContext = createContext<DrawerContextProps>({
  openDrawer: () => undefined,
  closeDrawer: () => undefined,
});

export type DrawerProviderProps = {
  children: ReactNode;
};

export default function DrawerProvider({ children }: DrawerProviderProps) {
  const [cfg, setCfg] = useState<DrawerConfig>({
    content: <></>,
    onClose: () => {},
    config: {},
  });
  const [open, setOpen] = useState(false);

  const openDrawer = (config: DrawerConfig) => {
    setCfg(() => config);
    setOpen(true);
  };

  const closeDrawer = (onClose: (close: Noop) => void) => {
    if (onClose) {
      onClose(() => setOpen(false));
      return;
    }

    setOpen(false);

    if (cfg.onClose) {
      cfg.onClose();
    }
  };

  const drawerProps = cfg.config ?? {};

  return (
    <DrawerContext.Provider value={{ openDrawer, closeDrawer }}>
      <Drawer
        anchor="right"
        keepMounted
        closeAfterTransition
        {...drawerProps}
        slotProps={{
          ...(drawerProps?.slotProps ?? {}),
          paper: {
            ...(drawerProps?.slotProps?.paper ?? {}),
            sx: {
              ...voc(boolVal(cfg.width), {
                width: '100%',
                maxWidth: cfg.width || '300px',
              }),
              //@ts-ignore
              ...(drawerProps?.slotProps?.paper?.sx ?? {}),
            },
          },
        }}
        swipeAreaWidth={0}
        open={open}
        onOpen={() => setOpen(true)}
        onClose={() => setOpen(false)}
      >
        {cfg.content}
      </Drawer>
      {children}
    </DrawerContext.Provider>
  );
}
