import { type RouteItems, router } from '../../../salvon/router';

export const authRoutes = {
  login: {
    path: '/login',
    file: 'login',
  },
  forgot_password: {
    path: '/forgot-password',
    file: 'forgot-password',
  },
  reset_password: {
    path: '/reset-password/:token',
    file: 'reset-password/[token]',
  },
} satisfies RouteItems;

export default router({
  layout: 'src/layout/auth/Layout.tsx',
  items: authRoutes,
  routesRootDirectory: 'src/routes/(auth)',
  routeFile: 'page.tsx',
});
