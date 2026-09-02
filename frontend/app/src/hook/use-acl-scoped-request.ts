import type { AsyncApiRequest } from '@salvon/api';
import { useRequest as useSalvonRequest } from '@salvon/hooks/useRequest';
import type { RoutePermission } from '@salvon/router';

import { useHasPermission } from '@app/hook/use-permissions';

const noop = (() =>
  Promise.resolve(null)) as unknown as () => Promise<AsyncApiRequest>;

export const useAclScopedRequest = (
  request: () => Promise<AsyncApiRequest>,
  preventStrictMode: boolean = false,
  permissions?: RoutePermission[],
) => {
  const hasPermission = useHasPermission();
  const allowed = !permissions?.length || hasPermission(permissions);
  const result = useSalvonRequest(allowed ? request : noop, preventStrictMode);

  return { ...result, allowed };
};
