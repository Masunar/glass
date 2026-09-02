import { appRoutes } from '@router/app-router';

import { useEffect } from 'react';

import { Flex } from '@salvon/components/div';
import { Loading } from '@salvon/components/progress';

import { AuthApi } from '@app/api/AuthApi';

export default function Page() {
  const handleLogout = async () => {
    await AuthApi.logout();
    window.location.href = appRoutes.index.path;
  };

  useEffect(() => {
    handleLogout();
  }, []);

  return (
    <Flex gap={'30px'} fw center sx={{ minHeight: '100vh' }} column>
      <img
        alt="synteco"
        src="https://www.synteco.pl/assets/images/logos/logo_synteco_black.svg"
        style={{ maxWidth: '120px', width: '100%' }}
      />
      <Loading sx={{ color: '#fab800' }} size={50} />
    </Flex>
  );
}
