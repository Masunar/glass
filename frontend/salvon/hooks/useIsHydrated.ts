import { useSyncExternalStore } from 'react';

export const useIsHydrated = (): boolean =>
  useSyncExternalStore(
    () => () => {},
    () => true,
    () => false,
  );
