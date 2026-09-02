import { type RouteItems, router } from '../../../salvon/router';

export const redirectRoutes = {
  logout: {
    path: '/logout',
    file: 'logout',
  },
} satisfies RouteItems;

export default router({
  items: redirectRoutes,
  routesRootDirectory: 'src/routes/(redirect)',
  routeFile: 'page.tsx',
});
