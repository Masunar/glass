import I18NProvider from './I18NProvider';
import LocaleProvider from './LocaleProvider';
import MenuControlProvider from './MenuControlProvider';
import NotificationProvider from './NotificationProvider';
import ThemeProvider from './ThemeProvider';
import type { i18n } from 'i18next';
import type { ReactNode } from 'react';

import ConfirmProvider from '@salvon/provider/ConfirmProvider';
import DrawerProvider from '@salvon/provider/DrawerProvider';
import ModalProvider from '@salvon/provider/ModalProvider';
import type { Theme } from '@salvon/types';

type OptionalThemeProvider =
  | {
      useDefaultThemeProvider?: true;
      theme: {
        lightTheme: Theme;
        darkTheme: Theme;
        defaultMode?: string;
        forceMode?: string;
      };
    }
  | {
      useDefaultThemeProvider?: false | undefined;
      theme?: undefined;
    };

export type ProvidersProps = {
  i18n: i18n;
  children: ReactNode;
  defaultLocale: string;
  localeLocalStorageKey?: string;
  compactModeDefault?: boolean;
} & OptionalThemeProvider;

export default function Providers({
  children,
  i18n,
  theme,
  defaultLocale,
  localeLocalStorageKey,
  compactModeDefault = false,
  useDefaultThemeProvider = true,
}: ProvidersProps) {
  children = <ConfirmProvider>{children}</ConfirmProvider>;
  children = <DrawerProvider>{children}</DrawerProvider>;
  children = <ModalProvider>{children}</ModalProvider>;
  children = <NotificationProvider>{children}</NotificationProvider>;

  if (useDefaultThemeProvider && theme) {
    children = <ThemeProvider {...theme}>{children}</ThemeProvider>;
  }

  return (
    <I18NProvider i18n={i18n}>
      <LocaleProvider
        defaultLocale={defaultLocale}
        localeLocalStorageKey={localeLocalStorageKey}
      >
        <MenuControlProvider compactModeDefault={compactModeDefault}>
          {children}
        </MenuControlProvider>
      </LocaleProvider>
    </I18NProvider>
  );
}
