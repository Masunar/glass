import { index, layout, route } from '@react-router/dev/routes';

import { startingEndingSlashes } from '../consts/regex';

export type RoutePermission = {
  permission: string;
  subPermission?: string;
};

export type ApplicationRoute = {
  file: string;
  id?: string;
  permissions?: RoutePermission[];
} & (
  | {
      path?: never;
      index: true;
    }
  | {
      path: string;
      index?: false;
    }
);

export type RouteItems = Record<string, ApplicationRoute>;

export type Routes = {
  layout?: string;
  prefix?: string;
  items: RouteItems;
  routesRootDirectory?: string;
  routeFile?: 'index.tsx' | 'page.tsx';
  languages?: string[];
};

export function router(config: Routes) {
  const languages = config.languages;
  const routes = languages
    ? [
        ...buildRoutes(config),
        ...languages.flatMap((iso) => buildRoutes(config, iso)),
      ]
    : buildRoutes(config);

  if (config.layout) {
    return [layout(config.layout, routes)];
  }

  return routes;
}

export function buildRoutes(config: Routes, langIso?: string) {
  let routesRootDirectory = config.routesRootDirectory ?? '';
  const items = Object.values(config.items);

  if (routesRootDirectory.length > 0) {
    routesRootDirectory = trimRoutePart(routesRootDirectory);

    if (routesRootDirectory.length > 0) {
      routesRootDirectory = `${routesRootDirectory}/`;
    }
  }

  return items.map((r, idx) => {
    const baseLocation = trimRoutePart(
      `${routesRootDirectory}${trimRoutePart(r.file)}`,
    );
    const autoInclude = config.routeFile && !baseLocation.endsWith('.tsx');

    const location = autoInclude
      ? `${baseLocation}/${config.routeFile}`
      : baseLocation;

    if (langIso && r.index) {
      return route(langIso, location, {
        index: true,
        id: `${r.id ?? ''}${location}-${langIso}-${idx}`,
      });
    }

    if (r.index) {
      return index(location);
    }

    let path = r.path;

    if (config.prefix && config.prefix.length > 0) {
      path = routePath(config.prefix, path);
    }

    if (langIso) {
      return route(routePath(langIso, path), location, {
        id: `${r.id ?? ''}${location}-${langIso}-${idx}`,
      });
    }

    return route(path, location, {
      id: r.id,
    });
  });
}

const trimRoutePart = (string: string) => {
  return string.replace(startingEndingSlashes, '');
};

export const routePath = (...parts: string[]) => {
  return '/' + parts.map(trimRoutePart).join('/');
};

export const prefixRoutes = <T extends RouteItems>(
  prefix: string,
  routes: T,
): T => {
  const result: RouteItems = {};

  for (const [key, route] of Object.entries(routes)) {
    if (route.path === undefined) {
      result[key] = route;
      continue;
    }

    result[key] = { ...route, path: routePath(prefix, route.path) };
  }

  return result as any;
};

export function parseLocalizedCatchAllRoute(
  pathname: string,
  languages: readonly string[],
  defaultLanguage: string | null = null,
): { lang: string | null; rest: string } {
  const segments = pathname.replace(startingEndingSlashes, '').split('/');
  const first = segments[0] ?? '';
  if ((languages as readonly string[]).includes(first)) {
    return { lang: first, rest: segments.slice(1).join('/') };
  }
  return { lang: defaultLanguage, rest: segments.join('/') };
}
