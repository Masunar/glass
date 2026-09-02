import { Avatar, Chip, Typography } from '@mui/material';

import { useState } from 'react';
import { BiChevronLeft } from 'react-icons/bi';
import { PiEnvelopeBold } from 'react-icons/pi';

import { Button } from '@salvon/components/button';
import { Card } from '@salvon/components/card';
import { Div, Flex } from '@salvon/components/div';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { notifyError, notifySuccess } from '@salvon/utils/notify';

import { MfaApi } from '@app/api/MfaApi';

type Props = {
  userEmail: string;
  onBack: () => void;
  onDisabled: () => void;
};

export const EmailManage = ({ userEmail, onBack, onDisabled }: Props) => {
  const t = useTranslation();
  const [loading, setLoading] = useState(false);

  const handleDisable = async () => {
    setLoading(true);
    const { response } = await MfaApi.disable();
    setLoading(false);
    if (response?.success) {
      notifySuccess(t('page.user_account.mfa.disabled'));
      onDisabled();
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
          {t('page.user_account.mfa.email_title')}
        </Typography>
      </Flex>

      <Card elevation={0}>
        <Flex align="center" gap={1.5}>
          <Avatar
            sx={{ background: '#0288d1', width: 44, height: 44 }}
            variant="rounded"
          >
            <PiEnvelopeBold />
          </Avatar>
          <Div>
            <Flex align="center" gap={1} wrap>
              <Typography variant="body1" sx={{ fontWeight: 600 }}>
                {t('page.user_account.mfa.email_detail_title')}
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
              {`${t('page.user_account.mfa.email_desc')} ${userEmail}`}
            </Typography>
          </Div>
        </Flex>
      </Card>

      <Button
        variant="contained"
        color="error"
        loading={loading}
        onClick={handleDisable}
        sx={{ width: '100%' }}
      >
        {t('page.user_account.mfa.disable_email')}
      </Button>
    </Flex>
  );
};
