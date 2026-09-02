import type { RoutePermission } from '@salvon/router';

import type { User } from '@app/auth/user';
import { SubPermission } from '@app/config/permission';
import { useUser } from '@app/hook/use-user';

export const userHasPermission = (
  user: User | undefined,
  permission?: string | RoutePermission[],
  sub?: SubPermission | string,
): boolean => {
  if (!user) {
    return false;
  }

  if (user.is_super_user || !permission) {
    return true;
  }

  const owned = (user.permissions ?? []).map((item) => item.name);

  const check = (entity: string, subPermission?: string): boolean => {
    const needed = subPermission ? `${entity}.${subPermission}` : entity;
    const [resource] = needed.split('.');

    return (
      owned.includes(needed) ||
      owned.includes(`${resource}.${SubPermission.WILDCARD}`)
    );
  };

  if (Array.isArray(permission)) {
    return permission.some((entry) =>
      check(entry.permission, entry.subPermission),
    );
  }

  return check(permission, sub);
};

export const useHasPermission = () => {
  const user = useUser();

  return (
    permission?: string | RoutePermission[],
    sub?: SubPermission | string,
  ): boolean => userHasPermission(user, permission, sub);
};

export const useMissingPermissions = () => {
  const hasPermission = useHasPermission();

  return (required: RoutePermission[]): string[] =>
    required
      .filter((entry) => !hasPermission(entry.permission, entry.subPermission))
      .map((entry) =>
        entry.subPermission
          ? `${entry.permission}.${entry.subPermission}`
          : entry.permission,
      );
};
