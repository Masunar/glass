import { useContext } from 'react';

import LocaleContext from '@salvon/context/LocaleContext';

export const useLocale = () => useContext(LocaleContext);

/**
 * Jezyk nigdy nie moze byc pusty: dayjs.locale('') zwraca nazwe jezyka,
 * a nie obiekt daty, wiec kolejne .format() wywraca render.
 */
export const useCurrentLocale = () => useLocale().locale || 'en';

export const useSetLocale = () => useLocale().setLocale;
