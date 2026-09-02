import { type Dispatch, type SetStateAction, useEffect, useState } from 'react';

import {
  getFromLocalStorage,
  saveToLocalStorage,
} from '@salvon/utils/local-storage';

export const useGetFromLocalStorage = <T>(
  key: string,
  defaultValue: any,
): [T, Dispatch<SetStateAction<T>>] => {
  const [storedValue, setStoredValue] = useState<T>(() => {
    return getFromLocalStorage(key, defaultValue);
  });

  useEffect(() => {
    window.localStorage.setItem(key, JSON.stringify(storedValue));
    saveToLocalStorage(key, storedValue);
  }, [key, storedValue]);

  return [storedValue, setStoredValue];
};
