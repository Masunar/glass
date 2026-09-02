import { Alert, Typography } from '@mui/material';

import { EmailManage } from './EmailManage';
import { EmailSetup } from './EmailSetup';
import { MethodCard } from './MethodCard';
import TotpSetup from './TotpSetup';
import { TotpManage } from './TotpManage';
import { useState } from 'react';
import { BiChevronLeft } from 'react-icons/bi';
import { MdLockOutline } from 'react-icons/md';
import { PiEnvelopeBold } from 'react-icons/pi';

import { Flex } from '@salvon/components/div';
import { useTranslation } from '@salvon/hooks/useTranslation';

import type { User } from '@app/auth/user';

type MfaView =
  | 'list'
  | 'totp_manage'
  | 'totp_setup'
  | 'email_manage'
  | 'email_setup';

type Props = {
  user: User;
  onBack: () => void;
  onRefresh: () => void;
  recovered?: boolean;
};

export default function Mfa({ user, onBack, onRefresh, recovered }: Props) {
  const [view, setView] = useState<MfaView>('list');
  const t = useTranslation();

  const totpActive = user.mfa_active === true && user.mfa_type === 'totp';
  const emailActive = user.mfa_active === true && user.mfa_type === 'email';

  const goBack = () => {
    onRefresh();
    setView('list');
  };

  if (view === 'totp_setup') {
    return (
      <Flex column gap={1}>
        <Flex
          align="center"
          gap={0.5}
          sx={{ cursor: 'pointer', color: 'text.secondary', mb: 0.5 }}
          onClick={goBack}
        >
          <BiChevronLeft style={{ fontSize: 18 }} />
          <Typography variant="body2">
            {t('page.user_account.btn_2fa')}
            {' / '}
            {t('page.user_account.mfa.totp_title')}
          </Typography>
        </Flex>
        <TotpSetup close={goBack} />
      </Flex>
    );
  }

  if (view === 'totp_manage') {
    return (
      <TotpManage
        onBack={goBack}
        onSetupDevice={() => setView('totp_setup')}
        onDisabled={goBack}
      />
    );
  }

  if (view === 'email_setup') {
    return <EmailSetup onBack={goBack} onActivated={goBack} />;
  }

  if (view === 'email_manage') {
    return (
      <EmailManage userEmail={user.email} onBack={goBack} onDisabled={goBack} />
    );
  }

  return (
    <Flex column gap={1.5}>
      <Flex
        align="center"
        gap={0.5}
        sx={{ cursor: 'pointer', color: 'text.secondary', mb: 0.5 }}
        onClick={onBack}
      >
        <BiChevronLeft style={{ fontSize: 18 }} />
        <Typography variant="body2">
          {t('page.user_account.back_to_account')}
          {' / '}
          {t('page.user_account.btn_2fa')}
        </Typography>
      </Flex>
      {recovered ? (
        <Alert severity="error" sx={{ mb: 0.5 }}>
          {t('page.mfa.recovery_success')}
        </Alert>
      ) : (
        <Alert severity="info" sx={{ mb: 0.5 }}>
          {t('page.user_account.mfa.info')}
        </Alert>
      )}
      <MethodCard
        icon={<PiEnvelopeBold />}
        iconBg="#0288d1"
        title={t('page.user_account.mfa.email_title')}
        description={`${t('page.user_account.mfa.email_desc')} ${user.email}`}
        active={emailActive}
        disabled={totpActive}
        onClick={() => setView(emailActive ? 'email_manage' : 'email_setup')}
      />
      <MethodCard
        icon={<MdLockOutline />}
        iconBg="#6a1b9a"
        title={t('page.user_account.mfa.totp_title')}
        description={t('page.user_account.mfa.totp_desc')}
        active={totpActive}
        disabled={emailActive}
        onClick={() => setView(totpActive ? 'totp_manage' : 'totp_setup')}
      />
    </Flex>
  );
}
