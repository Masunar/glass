import { Typography } from '@mui/material';

import TaskDrawerTitle from './TaskDrawerTitle';
import type { TaskDrawerProps } from './types.d';
import { MdClose } from 'react-icons/md';

import { Flex } from '@salvon/components/div';
import { IconButton } from '@salvon/components/icon-button';
import { useIsDarkMode } from '@salvon/hooks/useTheme';
import { useTranslation } from '@salvon/hooks/useTranslation';

type Props = { onClose: () => void } & Pick<
  TaskDrawerProps,
  'titleIcon' | 'title' | 'headerActions'
>;

export default function TaskDrawerHeader({
  onClose,
  titleIcon,
  title,
  headerActions,
}: Props) {
  const t = useTranslation();
  const isDark = useIsDarkMode();

  return (
    <Flex
      column
      fw
      sx={{
        position: 'relative',
        zIndex: 1,
        gap: 0.5,
        pb: 1.5,
        pt: 0.5,
        borderBottom: '1px solid',
        borderColor: isDark ? 'divider' : '#eef1f5',
      }}
    >
      <IconButton
        variant="mui"
        icon={<MdClose size={20} />}
        onClick={onClose}
        label={t('close')}
        sx={{
          position: 'absolute',
          top: -1,
          right: 0,
          p: 0.5,
          '&:hover': { backgroundColor: 'action.hover' },
        }}
      />

      {title?.subtitle && (
        <Typography
          sx={{
            fontSize: '0.64rem',
            fontWeight: 700,
            letterSpacing: '0.5px',
            textTransform: 'uppercase',
            color: isDark ? 'text.secondary' : '#a3adba',
          }}
        >
          {title.subtitle}
        </Typography>
      )}

      <Flex aCenter sx={{ gap: 0.5, pr: 4.5, minWidth: 0 }}>
        {titleIcon && (
          <Flex
            aCenter
            jCenter
            sx={{
              width: 32,
              height: 32,
              flexShrink: 0,
              borderRadius: '9px',
              border: '1px solid',
              borderColor: isDark ? 'rgba(255,255,255,0.08)' : '#e7ebf1',
              backgroundColor: isDark ? 'rgba(255,255,255,0.04)' : '#f8fafc',
              color: isDark ? 'text.secondary' : '#516385',
            }}
          >
            {titleIcon}
          </Flex>
        )}
        {title && (
          <TaskDrawerTitle
            title={title.title}
            editable={title.editable ?? true}
            onSave={title.onSave}
          />
        )}
        {headerActions}
      </Flex>
    </Flex>
  );
}
