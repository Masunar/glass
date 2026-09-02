import { Alert, Avatar, Chip, Typography } from '@mui/material';

import { useState } from 'react';
import { BiChevronLeft } from 'react-icons/bi';
import { MdLockOutline, MdPhoneAndroid, MdVpnKey } from 'react-icons/md';

import { Button } from '@salvon/components/button';
import { Card } from '@salvon/components/card';
import { Div, Flex } from '@salvon/components/div';
import { Modal } from '@salvon/components/modal';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { notifyError, notifySuccess } from '@salvon/utils/notify';

import { MfaApi } from '@app/api/MfaApi';

type Props = {
  onBack: () => void;
  onSetupDevice: () => void;
  onDisabled: () => void;
};

export const TotpManage = ({ onBack, onSetupDevice, onDisabled }: Props) => {
  const t = useTranslation();
  const [disableLoading, setDisableLoading] = useState(false);
  const [recoveryKey, setRecoveryKey] = useState<string | null>(null);
  const [recoveryLoading, setRecoveryLoading] = useState(false);
  const [recoveryOpen, setRecoveryOpen] = useState(false);

  const handleDisable = async () => {
    setDisableLoading(true);
    const { response } = await MfaApi.disable();
    setDisableLoading(false);
    if (response?.success) {
      notifySuccess(t('page.user_account.mfa.disabled'));
      onDisabled();
    } else {
      notifyError(t('api.ise'));
    }
  };

  const handleShowRecoveryKey = async () => {
    setRecoveryLoading(true);
    const { content, response } = await MfaApi.recoveryKey();
    setRecoveryLoading(false);
    if (response?.success) {
      setRecoveryKey(content?.data?.recovery_key ?? null);
      setRecoveryOpen(true);
    } else {
      notifyError(t('api.ise'));
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
          {t('page.user_account.mfa.totp_title')}
        </Typography>
      </Flex>

      <Card elevation={0}>
        <Flex align="center" gap={1.5}>
          <Avatar
            sx={{ background: '#6a1b9a', width: 44, height: 44 }}
            variant="rounded"
          >
            <MdLockOutline />
          </Avatar>
          <Div>
            <Flex align="center" gap={1} wrap>
              <Typography variant="body1" sx={{ fontWeight: 600 }}>
                {t('page.user_account.mfa.totp_detail_title')}
              </Typography>
              <Chip
                label={t('page.user_account.mfa.active')}
                size="small"
                sx={{
                  background: '#1b5e20',
                  color: '#fff',
                  fontWeight: 600,
                  fontSize: 11,
                }}
              />
            </Flex>
            <Typography variant="caption" sx={{ color: 'text.secondary' }}>
              {t('page.user_account.mfa.totp_detail_desc')}
            </Typography>
          </Div>
        </Flex>
      </Card>

      <Flex gap={1}>
        <Button
          variant="outlined"
          icon={<MdVpnKey />}
          sx={{ flex: 1, flexDirection: 'column', gap: 0.5, py: 1.5 }}
          loading={recoveryLoading}
          onClick={handleShowRecoveryKey}
        >
          {t('page.user_account.mfa.recovery_codes')}
        </Button>
        <Button
          variant="outlined"
          icon={<MdPhoneAndroid />}
          sx={{ flex: 1, flexDirection: 'column', gap: 0.5, py: 1.5 }}
          onClick={onSetupDevice}
        >
          {t('page.user_account.mfa.change_device')}
        </Button>
      </Flex>

      <Button
        variant="contained"
        color="error"
        loading={disableLoading}
        onClick={handleDisable}
        sx={{ width: '100%' }}
      >
        {t('page.user_account.mfa.disable_totp')}
      </Button>

      <Modal
        open={recoveryOpen}
        setOpen={setRecoveryOpen}
        closeOnBackdropClick
        closeOnEsc
        closeButton
        maxWidth="xs"
        title={t('page.user_account.mfa.recovery_key_title')}
      >
        <Flex column gap={2} pb={2}>
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
          <Button
            variant="outlined"
            onClick={() => setRecoveryOpen(false)}
            sx={{ width: '100%' }}
          >
            {t('page.user_account.mfa.recovery_key_done')}
          </Button>
        </Flex>
      </Modal>
    </Flex>
  );
};
