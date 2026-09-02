import { Typography } from '@mui/material';

import { type RbcEvent, formatEventTime } from '../internal';

import { Flex } from '@salvon/components/div';
import { useTranslation } from '@salvon/hooks/useTranslation';

export type AgendaEventRowProps<T> = {
  event: RbcEvent<T>;
  locale: string;
  onClick?: () => void;
};

export default function AgendaEventRow<T>({
  event,
  locale,
  onClick,
}: AgendaEventRowProps<T>) {
  const t = useTranslation();
  const color = event.color ?? 'var(--mui-palette-text-disabled)';

  return (
    <Flex
      aCenter
      onClick={onClick}
      sx={{
        gap: 1.5,
        py: 1,
        pl: 1.5,
        pr: 1,
        borderLeft: `3px solid ${color}`,
        borderRadius: '0 6px 6px 0',
        cursor: onClick ? 'pointer' : 'default',
        '&:hover': onClick ? { backgroundColor: 'action.hover' } : undefined,
      }}
    >
      <Flex column sx={{ minWidth: 0, flex: 1 }}>
        <Typography
          sx={{
            fontWeight: 600,
            fontSize: 14,
            overflow: 'hidden',
            textOverflow: 'ellipsis',
            whiteSpace: 'nowrap',
          }}
        >
          {event.title}
        </Typography>
      </Flex>
      <Typography
        sx={{
          fontSize: 13,
          color: 'text.secondary',
          whiteSpace: 'nowrap',
          flexShrink: 0,
        }}
      >
        {formatEventTime(
          event,
          locale,
          t('scheduler_all_day', { defaultValue: 'cały dzień' }),
        )}
      </Typography>
    </Flex>
  );
}
