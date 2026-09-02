import type { Locales } from '@salvon/types';
import { getFromLocalStorage } from '@salvon/utils/local-storage';

export const locales: Locales = [
  {
    identifier: 'pl',
    name: 'polish',
    title: 'Polski',
    flag: '🇵🇱',
  },
];

export const localStorageKey = 'locale';

export const defaultLocale: string = locales[0].identifier;

export const getStoredLocale = () => {
  return getFromLocalStorage(localStorageKey, defaultLocale);
};
