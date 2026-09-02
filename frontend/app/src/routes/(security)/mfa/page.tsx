import { Typography } from '@mui/material';
import { authRoutes } from '@router/auth-router';
import { redirectRoutes } from '@router/redirect-router';

import InsertCode from './_components/InsertCode';
import InsertRecoveryCode from './_components/InsertRecoveryCode';
import { useState } from 'react';
import { PiLockKey } from 'react-icons/pi';

import { Div, Flex } from '@salvon/components/div';
import { Loading } from '@salvon/components/progress';
import { useMounted } from '@salvon/hooks/useMounted';
import { useNavigate } from '@salvon/hooks/usePathNavigate';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { isUnauthorized } from '@salvon/utils/api';

import { MfaApi } from '@app/api/MfaApi';
import AltAccentIcon from '@app/components/layout/AltAccentIcon';

type Mode = 'mfa' | 'recovery';

export default function Page() {
  const [fetched, setFetched] = useState(false);
  const [mode, setMode] = useState<Mode>('mfa');
  const [hasRecoveryKey, setHasRecoveryKey] = useState(false);
  const [codeLength, setCodeLength] = useState(8);
  const navigate = useNavigate();
  const t = useTranslation();

  useMounted(() => init());

  const init = async () => {
    setFetched(false);
    const { content, response } = await MfaApi.requirement();

    if (isUnauthorized(response)) {
      navigate(authRoutes.login);
      return;
    }

    if (!content.data.type) {
      navigate(redirectRoutes.logout);
      return;
    }

    setHasRecoveryKey(content.data.type === 'totp');
    setCodeLength(content.data.type === 'totp' ? 6 : 8);
    setFetched(true);
  };

  return (
    <Flex column fh gap={1} justify="center" pt={2} fw>
      <Flex center column>
        <AltAccentIcon icon={<PiLockKey size={28} />} />
        <Typography
          variant="h4"
          sx={{
            fontWeight: 400,
            color: 'text.primary',
            textAlign: 'center',
            fontSize: '1.8rem',
          }}
        >
          {t('page.mfa.title')}
        </Typography>
        <Div fw>
          <Flex center mt={2}>
            <Flex
              column
              gap={1}
              sx={{
                maxWidth: {
                  xs: '100%',
                  sm: '90%',
                  md: 550,
                  lg: 600,
                  xl: 650,
                  fhd: 750,
                  uhd: 1000,
                },
              }}
              fw
            >
              {!fetched ? (
                <Flex center mb={3} mt={1}>
                  <Loading />
                </Flex>
              ) : mode === 'recovery' ? (
                <InsertRecoveryCode onBack={() => setMode('mfa')} />
              ) : (
                <InsertCode
                  codeLength={codeLength}
                  hasRecoveryKey={hasRecoveryKey}
                  onRecovery={() => setMode('recovery')}
                />
              )}
            </Flex>
          </Flex>
        </Div>
      </Flex>
    </Flex>
  );
}
