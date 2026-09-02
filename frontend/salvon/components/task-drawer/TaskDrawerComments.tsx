import { TextField, Typography } from '@mui/material';

import CommentList from './CommentList';
import TaskDrawerSection from './TaskDrawerSection';
import type { TaskDrawerCommentsProps } from './types.d';
import { useState } from 'react';
import { MdAddComment, MdOutlineChatBubbleOutline } from 'react-icons/md';

import { Flex } from '@salvon/components/div';
import { IconButton } from '@salvon/components/icon-button';
import { useTranslation } from '@salvon/hooks/useTranslation';

export default function TaskDrawerComments({
  items = [],
  loading = false,
  submitting = false,
  title,
  icon = <MdOutlineChatBubbleOutline size={15} />,
  defaultOpen = true,
  placeholder,
  emptyText,
  onSubmit,
  onDelete,
  open,
  setOpen,
}: TaskDrawerCommentsProps) {
  const t = useTranslation();
  const [value, setValue] = useState('');
  const [busy, setBusy] = useState(false);
  const sending = submitting || busy;

  const handleSubmit = async () => {
    if (!value.trim() || !onSubmit || sending) return;
    setBusy(true);
    try {
      const result = await onSubmit(value.trim());
      if (result !== false) setValue('');
    } finally {
      setBusy(false);
    }
  };

  return (
    <TaskDrawerSection
      title={title ?? t('comments')}
      icon={icon}
      defaultOpen={defaultOpen}
      open={open}
      setOpen={setOpen}
      action={
        <Typography sx={{ fontSize: 13, color: 'text.disabled' }}>
          {items.length}
        </Typography>
      }
    >
      {onSubmit && (
        <Flex sx={{ gap: 1, mb: 2 }}>
          <TextField
            fullWidth
            multiline
            minRows={2}
            variant="outlined"
            value={value}
            disabled={sending}
            placeholder={placeholder ?? t('comment')}
            onChange={(event) => setValue(event.target.value)}
            onKeyDown={(event) => {
              if (event.key === 'Enter' && (event.metaKey || event.ctrlKey)) {
                event.preventDefault();
                handleSubmit();
              }
            }}
            sx={{ '& .MuiOutlinedInput-root': { borderRadius: 2 } }}
          />
          <IconButton
            variant="mui"
            icon={<MdAddComment />}
            color="primary"
            label={t('save')}
            loading={sending}
            disabled={!value.trim()}
            onClick={handleSubmit}
            sx={{ alignSelf: 'flex-end' }}
          />
        </Flex>
      )}

      <CommentList
        items={items}
        loading={loading}
        emptyText={emptyText}
        onDelete={onDelete}
      />
    </TaskDrawerSection>
  );
}
