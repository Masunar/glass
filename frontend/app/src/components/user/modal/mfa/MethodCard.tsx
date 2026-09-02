import { Avatar, Chip, Typography } from '@mui/material';

import { BiChevronRight } from 'react-icons/bi';

import { Card } from '@salvon/components/card';
import { Flex } from '@salvon/components/div';
import { useTranslation } from '@salvon/hooks/useTranslation';

type Props = {
  icon: React.ReactNode;
  iconBg: string;
  title: string;
  description: string;
  active: boolean;
  onClick: () => void;
  disabled?: boolean;
};

export const MethodCard = ({
  icon,
  iconBg,
  title,
  description,
  active,
  onClick,
  disabled = false,
}: Props) => {
  const t = useTranslation();

  return (
    <Card
      onClick={disabled ? undefined : onClick}
      sx={{
        cursor: disabled ? 'default' : 'pointer',
        '&:hover': disabled ? {} : { opacity: 0.85 },
        opacity: disabled ? 0.5 : 1,
      }}
    >
      <Flex align="center" gap={2}>
        <Avatar
          sx={{ background: iconBg, width: 44, height: 44, flexShrink: 0 }}
          variant="rounded"
        >
          {icon}
        </Avatar>
        <Flex column sx={{ flex: 1, minWidth: 0, gap: 0.5 }}>
          <Flex align="center" justify="space-between" gap={1}>
            <Typography variant="body1" sx={{ fontWeight: 600 }}>
              {title}
            </Typography>
            <Flex align="center" gap={0.5} sx={{ flexShrink: 0 }}>
              <Chip
                label={
                  active
                    ? t('page.user_account.mfa.active')
                    : t('page.user_account.mfa.inactive')
                }
                size="small"
                sx={{
                  background: active ? '#1b5e20' : '#555',
                  color: '#fff',
                  fontWeight: 600,
                  fontSize: 11,
                }}
              />
              <BiChevronRight style={{ fontSize: 20 }} />
            </Flex>
          </Flex>
          <Typography variant="caption" sx={{ color: 'text.secondary' }}>
            {description}
          </Typography>
        </Flex>
      </Flex>
    </Card>
  );
};
