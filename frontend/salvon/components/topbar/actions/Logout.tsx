import { Tooltip } from '@mui/material';

import { PiSignOut } from 'react-icons/pi';

import { IconButton } from '@salvon/components/icon-button';
import { useTranslation } from '@salvon/hooks/useTranslation';
import type { Noop } from '@salvon/types';

type Props = {
  logout: Noop;
};

export default function Logout({ logout }: Props) {
  const t = useTranslation();

  return (
    <Tooltip title={t('logout')}>
      <IconButton onClick={logout}>
        <PiSignOut />
      </IconButton>
    </Tooltip>
  );
}
