import UserCard from './UserCard';
import { useUserModal } from './UserModalContext';

import { useMenuControl } from '@salvon/hooks/useMenuControl';

type Props = {
  compactMode?: boolean;
};

export default function User({ compactMode }: Props) {
  const { openModal } = useUserModal();
  const { mobileOpen, setMobileOpen } = useMenuControl();

  return (
    <div
      onClick={() => {
        openModal();
        if (mobileOpen) setMobileOpen(false);
      }}
    >
      <UserCard compactMode={compactMode} />
    </div>
  );
}
