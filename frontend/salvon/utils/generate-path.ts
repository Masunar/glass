import type { ApplicationRoute } from '@salvon/router';
import { isObject } from '@salvon/utils/type-check';

type RouteParams = {
  [key: string]: any;
};

export type GeneratePathUrl = string | ApplicationRoute;

export type GeneratePathParams = {
  route?: Record<string, any>;
  query?: RouteParams;
};

const generatePath = (url: GeneratePathUrl, params?: GeneratePathParams) => {
  let preparedUrl: string = isObject(url) ? (url?.path ?? '/') : url;
  const routeParams: RouteParams = params?.route ?? {};
  const queryParams: RouteParams = params?.query ?? {};

  Object.keys(routeParams).forEach((param) => {
    preparedUrl = preparedUrl.replace(`:${param}`, routeParams[param]);
  });

  if (Object.keys(queryParams).length > 0) {
    preparedUrl = `${preparedUrl}?${objectToQueryParams(queryParams)}`;
  }

  return preparedUrl;
};

const objectToQueryParams = (
  object: RouteParams,
  parentKey?: string,
): string => {
  const euc = encodeURIComponent;

  return Object.keys(object)
    .map((key) => {
      const fullKey = parentKey ? `${euc(parentKey)}[${euc(key)}]` : euc(key);

      if (typeof object[key] === 'object' && !Array.isArray(object[key])) {
        return objectToQueryParams(object[key], fullKey);
      }

      if (Array.isArray(object[key])) {
        return object[key]
          .map((value: any) => `${fullKey}[]=${euc(value)}`)
          .join('&');
      }

      return `${fullKey}=${euc(object[key])}`;
    })
    .join('&');
};

export default generatePath;
