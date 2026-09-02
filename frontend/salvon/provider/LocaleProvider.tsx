import { AdapterDayjs } from '@mui/x-date-pickers/AdapterDayjs';
import { LocalizationProvider } from '@mui/x-date-pickers/LocalizationProvider';

import 'dayjs/locale/en';
import 'dayjs/locale/pl';
import { type ReactNode, useEffect } from 'react';

import LocaleContext from '@salvon/context/LocaleContext';
import { useGetFromLocalStorage } from '@salvon/hooks/useGetFromLocalStorage';
import { useI18N } from '@salvon/hooks/useTranslation';

export type LocaleProviderProps = {
  children: ReactNode;
  defaultLocale: string;
  localeLocalStorageKey?: string;
};

export default function LocaleProvider({
  defaultLocale,
  children,
  localeLocalStorageKey,
}: LocaleProviderProps) {
  const i18n = useI18N();
  const [locale, setLocale] = useGetFromLocalStorage<string>(
    localeLocalStorageKey ?? 'locale',
    defaultLocale,
  );

  useEffect(() => {
    i18n.changeLanguage(locale).finally();
  }, [locale]);

  return (
    <LocaleContext.Provider value={{ locale, setLocale }}>
      <LocalizationProvider dateAdapter={AdapterDayjs} adapterLocale={locale}>
        {children}
      </LocalizationProvider>
    </LocaleContext.Provider>
  );
}
