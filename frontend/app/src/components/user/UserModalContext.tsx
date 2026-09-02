import { type ReactNode, createContext, useContext, useState } from 'react';

type Page = 'main' | 'password' | 'mfa' | 'edit';

type UserModalContextValue = {
  open: boolean;
  page: Page;
  mfaRecovered: boolean;
  openModal: (page?: Page) => void;
  closeModal: () => void;
  setPage: (page: Page) => void;
};

const UserModalContext = createContext<UserModalContextValue>({
  open: false,
  page: 'main',
  mfaRecovered: false,
  openModal: () => {},
  closeModal: () => {},
  setPage: () => {},
});

export const useUserModal = () => useContext(UserModalContext);

type ProviderProps = {
  children: ReactNode;
  defaultOpen?: boolean;
  defaultPage?: Page;
  mfaRecovered?: boolean;
};

export function UserModalProvider({
  children,
  defaultOpen = false,
  defaultPage = 'main',
  mfaRecovered = false,
}: ProviderProps) {
  const [open, setOpen] = useState(defaultOpen);
  const [page, setPage] = useState<Page>(defaultPage);
  const [recovered, setRecovered] = useState(mfaRecovered);

  const openModal = (p: Page = 'main') => {
    setPage(p);
    setOpen(true);
  };

  const closeModal = () => {
    setOpen(false);

    setTimeout(() => {
      if (mfaRecovered) {
        setRecovered(false);
      }
      setPage('main');
    }, 300);
  };

  return (
    <UserModalContext.Provider
      value={{
        open,
        page,
        mfaRecovered: recovered,
        openModal,
        closeModal,
        setPage,
      }}
    >
      {children}
    </UserModalContext.Provider>
  );
}
