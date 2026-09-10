import { appRoutes } from '@router/app-router';

import { Permission, SubPermission } from './permission';
import type { ModuleKey } from './tokens';

export type ModuleLink = {
  labelKey: string;
  path?: string;
  permission?: { permission: Permission; subPermission: SubPermission };
};

export type AppModule = {
  key: ModuleKey;
  /** Skrót na listwie — cztery znaki, bo tyle mieści się czytelnie. */
  code: string;
  labelKey: string;
  links: ModuleLink[];
};

/**
 * Podział aplikacji na moduły.
 *
 * Wynika z projektu nawigacji, nie z wygody: kolor listwy niesie
 * znaczenie, więc moduł musi odpowiadać obszarowi pracy, a nie
 * przypadkowej grupie ekranów. Stąd Cennik i Kontrahenci siedzą
 * wewnątrz Zleceń — handlowiec sięga po nie w trakcie wyceny, a nie
 * jako po osobne narzędzie.
 *
 * Moduły bez ani jednego działającego ekranu zostają na listwie
 * wyszarzone. To celowe: pokazują kształt systemu, ale nie udają,
 * że prowadzą dokądkolwiek.
 */
export const appModules: AppModule[] = [
  {
    key: 'zlec',
    code: 'ZLEC',
    labelKey: 'page.module.zlec',
    links: [
      {
        labelKey: 'page.menu.orders',
        path: appRoutes.orders.path,
        permission: {
          permission: Permission.ORDERS,
          subPermission: SubPermission.LIST,
        },
      },
      {
        labelKey: 'page.menu.contractors',
        path: appRoutes.contractors.path,
        permission: {
          permission: Permission.CONTRACTORS,
          subPermission: SubPermission.LIST,
        },
      },
      {
        labelKey: 'page.menu.price_list',
        path: appRoutes.priceList.path,
        permission: {
          permission: Permission.PRICE_LIST,
          subPermission: SubPermission.LIST,
        },
      },
      { labelKey: 'page.menu.designers' },
      { labelKey: 'page.menu.offers' },
    ],
  },
  { key: 'prod', code: 'PROD', labelKey: 'page.module.prod', links: [] },
  { key: 'mag', code: 'MAG', labelKey: 'page.module.mag', links: [] },
  { key: 'ksie', code: 'KSIĘ', labelKey: 'page.module.ksie', links: [] },
  { key: 'rap', code: 'RAP', labelKey: 'page.module.rap', links: [] },
  {
    key: 'adm',
    code: 'ADM',
    labelKey: 'page.module.adm',
    links: [
      {
        labelKey: 'page.menu.users',
        path: appRoutes.users.path,
        permission: {
          permission: Permission.USERS,
          subPermission: SubPermission.LIST,
        },
      },
      {
        labelKey: 'page.menu.dictionaries',
        path: appRoutes.dictionaries.path,
        permission: {
          permission: Permission.DICTIONARIES,
          subPermission: SubPermission.LIST,
        },
      },
      {
        labelKey: 'page.menu.parameters',
        path: appRoutes.parameters.path,
        permission: {
          permission: Permission.PARAMETERS,
          subPermission: SubPermission.LIST,
        },
      },
    ],
  },
];

export const hasScreens = (module: AppModule): boolean =>
  module.links.some((link) => link.path !== undefined);

/** Moduł, w którym leży bieżąca ścieżka; Zlecenia jako punkt wyjścia. */
export function moduleForPath(pathname: string): AppModule {
  const found = appModules.find((module) =>
    module.links.some(
      (link) => link.path !== undefined && link.path === pathname,
    ),
  );

  return found ?? appModules[0];
}
