import { useContext } from 'react';

import LocaleContext from '@salvon/context/LocaleContext';

export const useLocale = () => useContext(LocaleContext);

export const useCurrentLocale = () => useLocale().locale;

export const useSetLocale = () => useLocale().setLocale;
