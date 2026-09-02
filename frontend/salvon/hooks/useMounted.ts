import { useEffect } from 'react';

import type { Noop } from '@salvon/types';

export const useMounted = (callback: Noop) => {
  useEffect(() => {
    callback();
  }, []);
};
