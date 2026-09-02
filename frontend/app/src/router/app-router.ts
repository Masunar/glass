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
} satisfies RouteItems;

export default router({
  layout: 'src/layout/app/Layout.tsx',
  items: appRoutes,
  routesRootDirectory: 'src/routes/(app)',
  routeFile: 'page.tsx',
});
