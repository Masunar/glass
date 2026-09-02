import { Alert, Typography } from '@mui/material';

import { useState } from 'react';
import { MdLockOutline } from 'react-icons/md';
import { PiEye, PiLock } from 'react-icons/pi';

import { Button } from '@salvon/components/button';
import { Card } from '@salvon/components/card';
import { Div, Flex } from '@salvon/components/div';
import { PinPad } from '@salvon/components/pin-pad';
import { Loading } from '@salvon/components/progress';
import QrCode from '@salvon/components/qr-code/QrCode';
import { useRequest } from '@salvon/hooks/useRequest';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { isMfaInvalid } from '@salvon/utils/api';
import { notifyError, notifyWarning } from '@salvon/utils/notify';

import { MfaApi } from '@app/api/MfaApi';

export default function SetupTotp({ close }: any) {
  const t = useTranslation();
  const [saving, setSaving] = useState(false);
  const [value, setValue] = useState('');
  const [codeVisible, setCodeVisible] = useState(false);
  const [recoveryKey, setRecoveryKey] = useState<string | null>(null);
  const { data, loading } = useRequest(() => MfaApi.beginSetup(), true);

  const handleSubmit = async (v: string | undefined = undefined) => {
    setSaving(true);
    const { content, response } = await MfaApi.finishSetup({
      code: v ?? value,
    });
    setSaving(false);

    if (isMfaInvalid(content)) {
      notifyWarning(t('page.mfa.invalid_code'));
      return;
    }

    if (response.success) {
      setRecoveryKey(content?.data?.recovery_key ?? null);
      return;
    }

    notifyError(t('api.ise'));
  };

  if (loading) {
    return (
      <Flex center>
        <Card>
          <Div sx={{ padding: '80px' }}>
            <Loading />
          </Div>
        </Card>
      </Flex>
    );
  }

  if (recoveryKey) {
    return (
      <Flex column gap={2} pt={1} pb={2}>
        <Flex align="center" gap={1.5}>
          <MdLockOutline
            style={{ fontSize: 24, color: '#4caf50', flexShrink: 0 }}
          />
          <Typography variant="h6" sx={{ fontWeight: 700 }}>
            {t('page.user_account.mfa.recovery_key_title')}
          </Typography>
        </Flex>
        <Alert severity="warning">
          {t('page.user_account.mfa.recovery_key_desc')}
        </Alert>
        <Div
          sx={{
            background: '#1a1a1a',
            border: '1px solid #444',
            borderRadius: 2,
            padding: '14px 18px',
            fontFamily: 'monospace',
            fontSize: 15,
            letterSpacing: 2,
            textAlign: 'center',
            wordBreak: 'break-all',
            color: '#fff',
          }}
        >
          {recoveryKey}
        </Div>
        <Button variant="contained" onClick={close} sx={{ width: '100%' }}>
          {t('page.user_account.mfa.recovery_key_done')}
        </Button>
      </Flex>
    );
  }

  return (
    <Flex gap={3} column center pt={1} pb={2}>
      <Flex gap={1} column center>
        <Typography variant="subtitle1">
          {t('page.user_account.mfa.scan_code')}
        </Typography>
        <Card>
          <QrCode size={200} value={data?.url} />
        </Card>
      </Flex>
      <Flex gap={1} column>
        <Typography variant="subtitle1">
          {t('page.user_account.mfa.optional_rewrite_code')}
        </Typography>
        <Div
          sx={{
            border: '1px solid #cecece',
            padding: '5px 12px',
            borderRadius: 1,
            alignSelf: 'center',
          }}
        >
          {codeVisible ? (
            data?.code
          ) : (
            <Flex
              center
              gap={1}
              sx={{ cursor: 'pointer' }}
              onClick={() => setCodeVisible(true)}
            >
              {'*'.repeat((data?.code ?? '').length + 9)}
              <PiEye style={{ fontSize: 20 }} />
            </Flex>
          )}
        </Div>
      </Flex>
      <Flex center gap={1} column>
        <Typography variant="h6">
          {t('page.user_account.mfa.insert_code')}
        </Typography>
        <PinPad
          onChange={(v) => setValue(v)}
          onPaste={(v) => handleSubmit(v)}
        />
      </Flex>
      <Button
        disabled={value.length !== 6}
        loading={loading || saving}
        color="primary"
        icon={<PiLock />}
        onClick={() => handleSubmit()}
        sx={{ width: '100%' }}
      >
        {t('page.user_account.mfa.submit')}
      </Button>
    </Flex>
  );
}
