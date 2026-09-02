import { Avatar, CircularProgress, InputBase } from '@mui/material';

import type { TaskDrawerMessageBoxProps } from './types.d';
import { useState } from 'react';
import { PiPaperPlaneRightFill } from 'react-icons/pi';

import { Flex } from '@salvon/components/div';
import { useIsDarkMode } from '@salvon/hooks/useTheme';
import { useTranslation } from '@salvon/hooks/useTranslation';

export default function TaskDrawerMessageBox({
  onSend,
  placeholder,
  avatar,
  authorInitial = '?',
  loading = false,
  disabled = false,
}: TaskDrawerMessageBoxProps) {
  const t = useTranslation();
  const isDark = useIsDarkMode();

  const [value, setValue] = useState('');
  const [busy, setBusy] = useState(false);
  const sending = loading || busy;

  const handleSend = async () => {
    if (!value.trim() || sending || disabled) return;
    setBusy(true);
    try {
      const result = await onSend(value.trim());
      if (result !== false) setValue('');
    } finally {
      setBusy(false);
    }
  };

  return (
    <Flex
      aCenter
      fw
      sx={{
        gap: '10px',
        flexShrink: 0,
        px: '22px',
        py: '14px',
        borderTop: '1px solid',
        borderColor: isDark ? 'divider' : '#eef1f5',
        backgroundColor: isDark ? 'background.paper' : '#fbfcfe',
      }}
    >
      {avatar ?? (
        <Avatar
          sx={{
            width: 30,
            height: 30,
            fontSize: '0.74rem',
            fontWeight: 600,
            backgroundColor: '#e2e8f0',
            color: '#475569',
            flexShrink: 0,
          }}
        >
          {authorInitial}
        </Avatar>
      )}

      <InputBase
        fullWidth
        multiline
        maxRows={4}
        placeholder={placeholder ?? t('comment')}
        value={value}
        disabled={sending || disabled}
        onChange={(event) => setValue(event.target.value)}
        onKeyDown={(event) => {
          if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            handleSend();
          }
        }}
        sx={{
          flex: 1,
          fontSize: '0.84rem',
          border: '1px solid',
          borderColor: isDark ? 'divider' : '#e2e8f0',
          borderRadius: '8px',
          px: '12px',
          py: '9px',
          backgroundColor: 'background.paper',
        }}
      />

      <Flex
        aCenter
        jCenter
        onClick={handleSend}
        sx={{
          width: 38,
          height: 38,
          borderRadius: '8px',
          backgroundColor: 'primary.main',
          color: 'primary.contrastText',
          flexShrink: 0,
          cursor: sending || disabled || !value.trim() ? 'default' : 'pointer',
          opacity: !value.trim() && !sending ? 0.55 : 1,
          '&:hover': { backgroundColor: 'primary.dark' },
        }}
      >
        {sending ? (
          <CircularProgress size={16} sx={{ color: 'inherit' }} />
        ) : (
          <PiPaperPlaneRightFill size={16} />
        )}
      </Flex>
    </Flex>
  );
}
