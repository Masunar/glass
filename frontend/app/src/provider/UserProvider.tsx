import { type ReactNode, createContext, useState } from 'react';

import type { Noop } from '@salvon/types';

import { AuthApi } from '@app/api/AuthApi';
import type { User } from '@app/auth/user';
import { userName } from '@app/components/utils/user';

export type UserContextProps = {
  getUser: () => User | undefined;
  refreshUser: Noop;
};

export const UserContext = createContext<UserContextProps>({
  getUser: () => undefined,
  refreshUser: () => {},
});

export type UserProviderProps = {
  children: ReactNode;
  user: User;
};

export default function UserProvider({ children, user }: UserProviderProps) {
  const [usr, setUser] = useState(user);

  const refreshUser = async () => {
    const { content } = await AuthApi.user();
    setUser(content.data);
  };

  return (
    <UserContext.Provider
      value={{
        getUser: () => (usr ? { ...usr, name: userName(usr) } : undefined),
        refreshUser,
      }}
    >
      {children}
    </UserContext.Provider>
  );
}
