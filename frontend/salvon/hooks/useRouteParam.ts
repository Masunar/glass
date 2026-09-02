import { useParams } from 'react-router';

import { intVal } from '@salvon/utils/type-transform';

export const useRouteParam = (param: string): string | undefined => {
  const params = useRouteParams();

  return params[param] ?? undefined;
};

export const useRouteParams = () => {
  return useParams();
};

export const useRouteIdParam = (name: string): number => {
  return intVal(useRouteParam(name));
};
