import { Avatar, CircularProgress, Typography } from '@mui/material';

import { formatDateTime, initialOf } from './format';
import type { TaskDrawerComment } from './types.d';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { FiTrash } from 'react-icons/fi';

import { Flex } from '@salvon/components/div';
import { IconButton } from '@salvon/components/icon-button';
import { useIsDarkMode } from '@salvon/hooks/useTheme';
import { useTranslation } from '@salvon/hooks/useTranslation';

type Props = {
  items: TaskDrawerComment[];
  loading?: boolean;
  emptyText?: ReactNode;
  onDelete?: (id: string) => void | Promise<void>;
};

export default function CommentList({
  items,
  loading = false,
  emptyText,
  onDelete,
}: Props) {
  const t = useTranslation();
  const [deletingId, setDeletingId] = useState<string | null>(null);

  const handleDelete = async (id: string) => {
    if (!onDelete || deletingId !== null) return;
    setDeletingId(id);
    try {
      await onDelete(id);
    } finally {
      setDeletingId(null);
    }
  };

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
      {items.map((comment) => (
        <CommentRow
          key={comment.id}
          comment={comment}
          onDelete={onDelete ? handleDelete : undefined}
          deleting={deletingId !== null}
        />
      ))}
    </Flex>
  );
}

function CommentRow({
  comment,
  onDelete,
  deleting,
}: {
  comment: TaskDrawerComment;
  onDelete?: (id: string) => void;
  deleting: boolean;
}) {
  const t = useTranslation();
  const isDark = useIsDarkMode();

  return (
    <Flex sx={{ gap: 1.5 }}>
      {comment.avatar ?? (
        <Avatar
          sx={{
            width: 32,
            height: 32,
            fontSize: '0.85em',
            flexShrink: 0,
            backgroundColor: '#e2e8f0',
            color: '#475569',
          }}
        >
          {initialOf(comment.author)}
        </Avatar>
      )}
      <Flex
        column
        sx={{
          flex: 1,
          minWidth: 0,
          gap: 0.5,
          p: '10px 12px',
          borderRadius: '10px',
          border: '1px solid',
          borderColor: isDark ? 'divider' : '#eef1f5',
          backgroundColor: isDark ? 'rgba(255,255,255,0.04)' : '#f8fafc',
        }}
      >
        <Flex aCenter sx={{ gap: 1 }}>
          <Typography sx={{ fontWeight: 700, fontSize: 14 }}>
            {comment.author}
          </Typography>
          {comment.date !== undefined && (
            <Typography sx={{ fontSize: 12, color: 'text.secondary' }}>
              {formatDateTime(comment.date)}
            </Typography>
          )}
          {onDelete && (
            <IconButton
              variant="mui"
              size="small"
              icon={<FiTrash />}
              label={t('delete')}
              sx={{ p: 0, ml: 'auto', flexShrink: 0 }}
              disabled={deleting}
              onClick={() => onDelete(comment.id)}
            />
          )}
        </Flex>
        <Typography
          component="div"
          sx={{ fontSize: 14, color: 'text.primary', whiteSpace: 'pre-wrap' }}
        >
          {comment.content}
        </Typography>
      </Flex>
    </Flex>
  );
}
