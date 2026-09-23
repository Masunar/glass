import { type ApplicationRoute, router } from '../../../salvon/router';
import { Permission, SubPermission } from '../config/permission';
import type { ModuleKey } from '../config/tokens';

/**
 * Trasa aplikacji razem z modułem, do którego należy.
 *
 * Moduł nie jest tu ozdobą: dostęp do modułu jest **poziomem nad**
 * dostępem do strony (U-04), więc trasa musi wiedzieć, o który
 * `*.access` pytać. Bez tego `zlec.access` chowałoby tylko kafelek na
 * listwie, a wklejony adres otwierałby ekran mimo wszystko.
 *
 * To samo przypisanie żyje po stronie PHP w `AccessRegistry::PAGES`.
 * Dwa źródła prawdy rozjechałyby się przy pierwszej nowej trasie,
 * więc `AccessCoverageTest` porównuje je wprost.
 */
export type AppRoute = ApplicationRoute & { module?: ModuleKey };

export const appRoutes = {
  index: {
    path: '/',
    file: 'index',
  },
  users: {
    path: '/users',
    module: 'adm',
    file: 'users',
    permissions: [
      { permission: Permission.USERS, subPermission: SubPermission.LIST },
    ],
  },
  parameters: {
    path: '/parameters',
    module: 'adm',
    file: 'parameters',
    permissions: [
      { permission: Permission.PARAMETERS, subPermission: SubPermission.LIST },
    ],
  },
  orders: {
    path: '/orders',
    module: 'zlec',
    file: 'orders',
    permissions: [
      { permission: Permission.ORDERS, subPermission: SubPermission.LIST },
    ],
  },
  orderCard: {
    path: '/orders/:id',
    module: 'zlec',
    file: 'orders/detail',
    permissions: [
      { permission: Permission.ORDERS, subPermission: SubPermission.LIST },
    ],
  },
  orderPanes: {
    path: '/orders/:id/formatki',
    module: 'zlec',
    file: 'orders/formatki',
    permissions: [
      { permission: Permission.ORDERS, subPermission: SubPermission.LIST },
    ],
  },
  orderDrawings: {
    path: '/orders/:id/rysunki',
    module: 'zlec',
    file: 'orders/rysunki',
    permissions: [
      { permission: Permission.ORDERS, subPermission: SubPermission.LIST },
    ],
  },
  orderPayments: {
    path: '/orders/:id/platnosci',
    module: 'zlec',
    file: 'orders/platnosci',
    permissions: [
      { permission: Permission.ORDERS, subPermission: SubPermission.LIST },
    ],
  },
  access: {
    path: '/access',
    module: 'adm',
    file: 'access',
    permissions: [
      { permission: Permission.ROLES, subPermission: SubPermission.LIST },
    ],
  },
  accessRole: {
    path: '/access/roles/:id',
    module: 'adm',
    file: 'access/role',
    permissions: [
      { permission: Permission.ROLES, subPermission: SubPermission.LIST },
    ],
  },
  orderOffers: {
    path: '/orders/:id/oferty',
    module: 'zlec',
    file: 'orders/oferty',
    permissions: [
      { permission: Permission.OFFERS, subPermission: SubPermission.LIST },
    ],
  },
  offers: {
    path: '/offers',
    module: 'zlec',
    file: 'offers',
    permissions: [
      { permission: Permission.OFFERS, subPermission: SubPermission.LIST },
    ],
  },
  orderLog: {
    path: '/orders/:id/dziennik',
    module: 'zlec',
    file: 'orders/dziennik',
    permissions: [
      { permission: Permission.ORDERS, subPermission: SubPermission.LIST },
    ],
  },
  production: {
    path: '/produkcja',
    module: 'prod',
    file: 'produkcja',
    permissions: [
      { permission: Permission.PRODUCTION, subPermission: SubPermission.LIST },
    ],
  },
  warehouse: {
    path: '/magazyn',
    module: 'mag',
    file: 'magazyn',
    permissions: [
      { permission: Permission.WAREHOUSE, subPermission: SubPermission.LIST },
    ],
  },
  tempering: {
    path: '/hartownia',
    module: 'prod',
    file: 'hartownia',
    permissions: [
      { permission: Permission.TEMPERING, subPermission: SubPermission.LIST },
    ],
  },
  contractors: {
    path: '/contractors',
    module: 'zlec',
    file: 'contractors',
    permissions: [
      { permission: Permission.CONTRACTORS, subPermission: SubPermission.LIST },
    ],
  },
  dictionaries: {
    path: '/dictionaries',
    module: 'adm',
    file: 'dictionaries',
    permissions: [
      {
        permission: Permission.DICTIONARIES,
        subPermission: SubPermission.LIST,
      },
    ],
  },
  priceList: {
    path: '/price-list',
    module: 'zlec',
    file: 'price-list',
    permissions: [
      { permission: Permission.PRICE_LIST, subPermission: SubPermission.LIST },
    ],
  },
} satisfies Record<string, AppRoute>;

export default router({
  layout: 'src/layout/app/Layout.tsx',
  items: appRoutes,
  routesRootDirectory: 'src/routes/(app)',
  routeFile: 'page.tsx',
});
