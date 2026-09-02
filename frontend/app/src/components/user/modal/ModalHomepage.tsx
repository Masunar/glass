import { Avatar, Badge, Chip, Typography } from '@mui/material';

import ActionButton from './ActionButton';
import { MdEdit, MdShield, MdVerifiedUser } from 'react-icons/md';
import { PiPasswordFill } from 'react-icons/pi';

import { Flex } from '@salvon/components/div';
import { useTranslation } from '@salvon/hooks/useTranslation';

import type { User } from '@app/auth/user';

type Props = {
  user: User;
  onPassword: () => void;
  onMfa: () => void;
  onEdit: () => void;
};

export default function ModalHomepage({
  user,
  onPassword,
  onMfa,
  onEdit,
}: Props) {
  const t = useTranslation();
  const hasMfa = user.mfa_active === true;
  const noMfaKnown = user.mfa_active !== undefined;

  return (
    <Flex column center gap={2} pb={1}>
      <Badge
        overlap="circular"
        anchorOrigin={{ vertical: 'bottom', horizontal: 'right' }}
        badgeContent={
          noMfaKnown ? (
            <Avatar
              sx={{
                width: 22,
                height: 22,
                background: hasMfa ? '#4caf50' : '#f44336',
                border: '2px solid #1e1e1e',
              }}
            >
              <MdVerifiedUser style={{ fontSize: 12 }} />
            </Avatar>
          ) : null
        }
      >
        <Avatar
          sx={{ width: 90, height: 90, fontSize: 36, background: '#254a94' }}
        >
          {(user.name?.[0] ?? '').toUpperCase()}
        </Avatar>
      </Badge>

      <Flex column center gap={0.5}>
        <Typography variant="h6" sx={{ fontWeight: 700 }}>
          {user.name}
        </Typography>
        <Typography variant="body2" sx={{ color: 'text.secondary' }}>
          {user.email}
        </Typography>
      </Flex>

      <Flex center gap={1} wrap>
        <Chip
          label={
            user.is_super_user
              ? t('page.user_account.role_admin')
              : t('page.user_account.role_user')
          }
          size="small"
          color="primary"
          sx={{ fontWeight: 600, fontSize: 11 }}
        />
        {user.mfa_active && user.mfa_type === 'email' && (
          <Chip
            label={t('page.user_account.mfa_badge_email')}
            size="small"
            sx={{
              background: '#1b5e20',
              color: '#fff',
              fontWeight: 600,
              fontSize: 11,
            }}
          />
        )}
        {user.mfa_active && user.mfa_type === 'totp' && (
          <Chip
            label={t('page.user_account.mfa_badge_totp')}
            size="small"
            sx={{
              background: '#1b5e20',
              color: '#fff',
              fontWeight: 600,
              fontSize: 11,
            }}
          />
        )}
      </Flex>

      <Flex gap={2} fw justify="center" mt={1}>
        <ActionButton
          icon={<PiPasswordFill />}
          label={t('page.user_account.btn_password')}
          color="#e65100"
          onClick={onPassword}
        />
        <ActionButton
          icon={<MdShield />}
          label={t('page.user_account.btn_2fa')}
          color="#0288d1"
          onClick={onMfa}
        />
        <ActionButton
          icon={<MdEdit />}
          label={t('page.user_account.btn_edit')}
          color="#6a1b9a"
          onClick={onEdit}
        />
      </Flex>
    </Flex>
  );
}
