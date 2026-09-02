import { Alert, Typography } from '@mui/material';

import { useState } from 'react';
import { BiChevronLeft } from 'react-icons/bi';

import { Button } from '@salvon/components/button';
import { Flex } from '@salvon/components/div';
import { PinPad } from '@salvon/components/pin-pad';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { notifyError, notifySuccess } from '@salvon/utils/notify';

import { MfaApi } from '@app/api/MfaApi';

type Props = {
  onBack: () => void;
  onActivated: () => void;
};

export const EmailSetup = ({ onBack, onActivated }: Props) => {
  const t = useTranslation();
  const [sent, setSent] = useState(false);
  const [sending, setSending] = useState(false);
  const [verifying, setVerifying] = useState(false);
  const [code, setCode] = useState('');

  const handleSend = async () => {
    setSending(true);
    const { response } = await MfaApi.beginEmailSetup();
    setSending(false);
    if (response?.success) {
      notifySuccess(t('page.user_account.mfa.email_sent'));
      setSent(true);
    } else {
      notifyError(t('api.ise'));
    }
  };

  const handleVerify = async () => {
    setVerifying(true);
    const { response } = await MfaApi.finishEmailSetup({ code });
    setVerifying(false);
    if (response?.success) {
      notifySuccess(t('page.user_account.mfa.email_activated'));
      onActivated();
    } else {
      notifyError(t('page.mfa.invalid_code'));
    }
  };

  return (
    <Flex column gap={2}>
      <Flex
        align="center"
        gap={0.5}
        sx={{ cursor: 'pointer', color: 'text.secondary' }}
        onClick={onBack}
      >
        <BiChevronLeft style={{ fontSize: 18 }} />
        <Typography variant="body2">
          {t('page.user_account.btn_2fa')}
          {' / '}
          {t('page.user_account.mfa.email_title')}
        </Typography>
      </Flex>

      <Alert severity="info">
        {t('page.user_account.mfa.email_setup_desc')}
      </Alert>

      {!sent ? (
        <Button
          variant="contained"
          loading={sending}
          onClick={handleSend}
          sx={{ width: '100%' }}
        >
          {t('page.user_account.mfa.email_send')}
        </Button>
      ) : (
        <Flex column gap={2}>
          <Typography
            variant="subtitle2"
            sx={{
              fontWeight: 600,
              color: 'text.secondary',
              textTransform: 'uppercase',
              letterSpacing: 1,
            }}
          >
            {t('page.user_account.mfa.email_verify')}
          </Typography>
          <Flex center>
            <PinPad onChange={setCode} length={8} height="48px" gap="8px" />
          </Flex>
          <Button
            variant="contained"
            loading={verifying}
            disabled={code.length < 8}
            onClick={handleVerify}
            sx={{ width: '100%' }}
          >
            {t('page.user_account.mfa.submit')}
          </Button>
        </Flex>
      )}
    </Flex>
  );
};
