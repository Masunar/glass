import { redirectRoutes } from '@router/redirect-router';

import { useRef, useState } from 'react';
import { PiLock, PiShield, PiSignOut } from 'react-icons/pi';

import { Button } from '@salvon/components/button';
import { Flex } from '@salvon/components/div';
import { PinPad, type PinPadRef } from '@salvon/components/pin-pad';
import { useIsOver } from '@salvon/hooks/useMediaQuery';
import { useSearchParam } from '@salvon/hooks/useSearchParams';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { isMfaInvalid } from '@salvon/utils/api';
import { notifyError, notifyWarning } from '@salvon/utils/notify';

import { MfaApi } from '@app/api/MfaApi';

type Props = {
  codeLength: number;
  hasRecoveryKey: boolean;
  onRecovery: () => void;
};

export default function InsertCode({
  codeLength,
  hasRecoveryKey,
  onRecovery,
}: Props) {
  const [loading, setLoading] = useState(false);
  const [value, setValue] = useState('');
  const returnTo = useSearchParam('return_to');
  const t = useTranslation();
  const isOverSm = useIsOver('sm');

  const pinPadRef = useRef<PinPadRef | null>(null);

  const handleSubmit = async (v: string | undefined = undefined) => {
    if (loading) {
      return;
    }

    setLoading(true);
    const { content, response } = await MfaApi.verify({ code: v ?? value });
    if (isMfaInvalid(content)) {
      setLoading(false);
      notifyWarning(t('page.mfa.invalid_code'));
      return;
    }

    if (response.success) {
      window.location.href = returnTo ?? '/';
      return;
    }
    setTimeout(() => {
      setLoading(false);
    }, 200);
    notifyError(t('api.ise'));
  };

  return (
    <Flex column gap={1} fw onPaste={(e) => pinPadRef.current?.onPaste(e)}>
      <Flex center mb={3} mt={1}>
        <PinPad
          fullWidth={!isOverSm || codeLength > 6}
          length={codeLength}
          ref={pinPadRef}
          onChange={(v) => setValue(v)}
          autofocus
          onLastInputChange={(v) => handleSubmit(v)}
          onPaste={(v) => handleSubmit(v)}
        />
      </Flex>
      <Button
        loading={loading}
        color="primary"
        icon={<PiLock />}
        disabled={value.length !== codeLength}
        onClick={() => handleSubmit()}
      >
        {t('page.mfa.submit')}
      </Button>
      <Flex justify="space-between" gap={1} fw sx={{ mt: 1 }} wrap>
        <Button
          fullWidth={!hasRecoveryKey}
          icon={<PiSignOut />}
          variant="outlined"
          path={redirectRoutes.logout}
          sx={hasRecoveryKey ? { fontSize: '14px', padding: '3px 8px' } : {}}
        >
          {t('page.mfa.logout')}
        </Button>
        {hasRecoveryKey && (
          <Button
            icon={<PiShield />}
            variant="outlined"
            color="error"
            sx={{ fontSize: '14px', padding: '3px 8px' }}
            onClick={onRecovery}
          >
            {t('page.mfa.recovery')}
          </Button>
        )}
      </Flex>
    </Flex>
  );
}
