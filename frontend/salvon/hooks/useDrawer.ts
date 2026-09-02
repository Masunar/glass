import { use } from 'react';

import { DrawerContext } from '@salvon/provider/DrawerProvider';

const useDrawerContext = () => use(DrawerContext);

export const useDrawer = () => {
  const { openDrawer, closeDrawer } = useDrawerContext();

  return [openDrawer, closeDrawer] as const;
};
