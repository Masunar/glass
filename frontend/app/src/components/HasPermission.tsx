import type { ReactNode } from 'react';

import type { SubPermission } from '@app/config/permission';
import { useHasPermission } from '@app/hook/use-permissions';

type Props = {
  permission: string;
  sub?: SubPermission | string;
  children: ReactNode;
};

export default function HasPermission({ permission, sub, children }: Props) {
  const permitted = useHasPermission();

  if (permitted(permission, sub)) {
    return children;
  }

  return <></>;
}
