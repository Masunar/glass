import { type RouteItems, router } from '../../../salvon/router';

export const securityRoutes = {
  mfa: {
    path: '/mfa',
    file: '(security)/mfa',
  },
} satisfies RouteItems;

export default router({
  layout: 'src/layout/security/Layout.tsx',
  items: securityRoutes,
  routesRootDirectory: 'src/routes',
  routeFile: 'page.tsx',
});
