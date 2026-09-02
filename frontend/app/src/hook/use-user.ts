import { use } from 'react';

import type { User } from '@app/auth/user';
import { UserContext } from '@app/provider/UserProvider';

export const useUser = (): User | undefined => use(UserContext).getUser();
export const useRefreshUser = () => use(UserContext).refreshUser;

export const useIsSuperUser = () => {
  const user = useUser();

  return user?.is_super_user ?? false;
};
