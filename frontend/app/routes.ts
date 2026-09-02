import { type RouteConfig } from '@react-router/dev/routes';

import app from './src/router/app-router';
import auth from './src/router/auth-router';
import redirect from './src/router/redirect-router';
import security from './src/router/security-router';

export default [
  ...app,
  ...auth,
  ...redirect,
  ...security,
] satisfies RouteConfig;
