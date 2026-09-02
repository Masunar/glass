import { Typography } from '@mui/material';

import { PiShieldCheck } from 'react-icons/pi';

import { Div, Flex } from '@salvon/components/div';
import { useIsDarkMode } from '@salvon/hooks/useTheme';
import { useTranslation } from '@salvon/hooks/useTranslation';

import { useUser } from '@app/hook/use-user';

type Props = {
  compactMode?: boolean;
};

export default function UserCard({ compactMode }: Props) {
  const user = useUser();
  const t = useTranslation();
  const isDark = useIsDarkMode();
  const unLetter = user?.name ? user.name.charAt(0).toUpperCase() : 'A';

  const card = {
    bg: isDark ? '#0f0f0f' : '#ffffff',
    border: isDark ? '#2a2a2a' : '#e7ebf1',
    hover: isDark ? '#151515' : '#f8fafc',
  };

  const avatar = (
    <Flex
      center
      sx={{
        width: 40,
        height: 40,
        borderRadius: '10px',
        background: '#254a94',
        color: '#fff',
        fontWeight: 700,
        fontSize: '1rem',
        flexShrink: 0,
      }}
    >
      {unLetter}
    </Flex>
  );

  if (compactMode) {
    return (
      <Flex center sx={{ py: 1, cursor: 'pointer' }} title={t('my_account')}>
        {avatar}
      </Flex>
    );
  }

  return (
    <Flex
      aCenter
      sx={{
        gap: 1.5,
        p: 1.25,
        borderRadius: '12px',
        border: `1px solid ${card.border}`,
        background: card.bg,
        cursor: 'pointer',
        transition: 'background 150ms',
        '&:hover': { background: card.hover },
      }}
    >
      {avatar}
      <Div sx={{ minWidth: 0 }}>
        <Typography
          noWrap
          sx={{
            fontSize: '0.9rem',
            fontWeight: 700,
            color: 'text.primary',
          }}
        >
          {user?.name}
        </Typography>
        <Flex aCenter sx={{ gap: 0.5, color: '#3b6fd4', minWidth: 0 }}>
          <PiShieldCheck size={13} style={{ flexShrink: 0 }} />
          <Typography
            noWrap
            sx={{ fontSize: '0.78rem', fontWeight: 600, color: '#3b6fd4' }}
          >
            {user?.email}
          </Typography>
        </Flex>
      </Div>
    </Flex>
  );
}
