import { type RouteItems, router } from '../../../salvon/router';
import { Permission, SubPermission } from '../config/permission';

export const appRoutes = {
  index: {
    path: '/',
    file: 'index',
  },
  users: {
    path: '/users',
    file: 'users',
    permissions: [
      { permission: Permission.USERS, subPermission: SubPermission.LIST },
    ],
  },
  parameters: {
    path: '/parameters',
    file: 'parameters',
    permissions: [
      { permission: Permission.PARAMETERS, subPermission: SubPermission.LIST },
    ],
  },
  orders: {
    path: '/orders',
    file: 'orders',
    permissions: [
      { permission: Permission.ORDERS, subPermission: SubPermission.LIST },
    ],
  },
  orderCard: {
    path: '/orders/:id',
    file: 'orders/detail',
    permissions: [
      { permission: Permission.ORDERS, subPermission: SubPermission.LIST },
    ],
  },
  orderPanes: {
    path: '/orders/:id/formatki',
    file: 'orders/formatki',
    permissions: [
      { permission: Permission.ORDERS, subPermission: SubPermission.LIST },
    ],
  },
  orderDrawings: {
    path: '/orders/:id/rysunki',
    file: 'orders/rysunki',
    permissions: [
      { permission: Permission.ORDERS, subPermission: SubPermission.LIST },
    ],
  },
  orderPayments: {
    path: '/orders/:id/platnosci',
    file: 'orders/platnosci',
    permissions: [
      { permission: Permission.ORDERS, subPermission: SubPermission.LIST },
    ],
  },
  orderLog: {
    path: '/orders/:id/dziennik',
    file: 'orders/dziennik',
    permissions: [
      { permission: Permission.ORDERS, subPermission: SubPermission.LIST },
    ],
  },
  contractors: {
    path: '/contractors',
    file: 'contractors',
    permissions: [
      { permission: Permission.CONTRACTORS, subPermission: SubPermission.LIST },
    ],
  },
  dictionaries: {
    path: '/dictionaries',
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
    file: 'price-list',
    permissions: [
      { permission: Permission.PRICE_LIST, subPermission: SubPermission.LIST },
    ],
  },
} satisfies RouteItems;

export default router({
  layout: 'src/layout/app/Layout.tsx',
  items: appRoutes,
  routesRootDirectory: 'src/routes/(app)',
  routeFile: 'page.tsx',
});
