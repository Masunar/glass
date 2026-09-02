import { Avatar, CircularProgress, Typography } from '@mui/material';

import { formatDateTime, initialOf } from './format';
import type { TaskDrawerActivityEntry } from './types.d';
import type { ReactNode } from 'react';

import { Div, Flex } from '@salvon/components/div';
import { useTranslation } from '@salvon/hooks/useTranslation';

type Props = {
  items: TaskDrawerActivityEntry[];
  loading?: boolean;
  emptyText?: ReactNode;
};

export default function ActivityList({
  items,
  loading = false,
  emptyText,
}: Props) {
  const t = useTranslation();

  if (loading) {
    return (
      <Flex jCenter sx={{ py: 3 }}>
        <CircularProgress size={22} />
      </Flex>
    );
  }

  if (items.length === 0) {
    return (
      <Flex center sx={{ py: 3 }}>
        <Typography sx={{ fontSize: 13, color: 'text.disabled' }}>
          {emptyText ?? t('no_results')}
        </Typography>
      </Flex>
    );
  }

  return (
    <Flex column sx={{ gap: 2 }}>
      {items.map((entry) => (
        <Flex column key={entry.id}>
          <Flex aCenter sx={{ gap: 1 }}>
            {entry.avatar ?? (
              <Avatar
                sx={{
                  width: 24,
                  height: 24,
                  fontSize: '0.8em',
                  backgroundColor: '#e2e8f0',
                  color: '#475569',
                }}
              >
                {initialOf(entry.actor)}
              </Avatar>
            )}
            <Flex column>
              {entry.actor && (
                <Div sx={{ fontWeight: 600, fontSize: 14 }}>{entry.actor}</Div>
              )}
              {entry.date !== undefined && (
                <Div sx={{ fontSize: 12, color: 'text.secondary' }}>
                  {formatDateTime(entry.date)}
                </Div>
              )}
            </Flex>
          </Flex>
          <Div sx={{ ml: 4, fontSize: 14, color: 'text.primary' }}>
            {entry.description}
          </Div>
        </Flex>
      ))}
    </Flex>
  );
}
