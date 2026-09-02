import { useSearchParams as useRouterSearchParams } from 'react-router';

export const useSearchParams = () => {
  const [params] = useRouterSearchParams();

  return Object.fromEntries(params.entries());
};

export const useSearchParam = (
  name: string,
  defaultValue: string | null | undefined = undefined,
) => {
  const [params] = useRouterSearchParams();

  return params.get(name) ?? defaultValue;
};
