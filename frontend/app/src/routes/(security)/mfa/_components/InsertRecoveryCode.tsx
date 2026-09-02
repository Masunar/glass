import { TextField } from '@mui/material';

import { useState } from 'react';
import { PiLock, PiLockKey } from 'react-icons/pi';

import { Button } from '@salvon/components/button';
import { Flex } from '@salvon/components/div';
import { useSearchParam } from '@salvon/hooks/useSearchParams';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { isMfaInvalid } from '@salvon/utils/api';
import { notifyError, notifyWarning } from '@salvon/utils/notify';

import { MfaApi } from '@app/api/MfaApi';

type Props = {
  onBack: () => void;
};

export default function InsertRecoveryCode({ onBack }: Props) {
  const [loading, setLoading] = useState(false);
  const [value, setValue] = useState('');
  const returnTo = useSearchParam('return_to');
  const t = useTranslation();

  const handleSubmit = async () => {
    if (loading) {
      return;
    }

    setLoading(true);
    const { content, response } = await MfaApi.recoveryAccount({
      recovery_key: value,
    });
    if (isMfaInvalid(content)) {
      setLoading(false);
      notifyWarning(t('page.mfa.invalid_code'));
      return;
    }

    if (response.success) {
      const target = new URL(returnTo ?? '/', window.location.origin);
      target.searchParams.set('mfa_recovered', '1');
      window.location.href = target.pathname + target.search;
      return;
    }
    setTimeout(() => {
      setLoading(false);
    }, 200);
    notifyError(t('api.ise'));
  };

  return (
    <Flex column gap={1} fw>
      <Flex center mb={3} mt={1} fw>
        <TextField
          fullWidth
          autoFocus
          value={value}
          onChange={(e) => setValue(e.target.value)}
          onKeyDown={(e) => e.key === 'Enter' && handleSubmit()}
          label={t('page.mfa.recovery_code')}
        />
      </Flex>
      <Button
        loading={loading}
        color="primary"
        icon={<PiLock />}
        disabled={!value.trim()}
        onClick={() => handleSubmit()}
      >
        {t('page.mfa.submit')}
      </Button>
      <Flex fw sx={{ mt: 1 }}>
        <Button fullWidth variant="outlined" onClick={onBack}>
          {t('page.mfa.back')}
        </Button>
      </Flex>
    </Flex>
  );
}
