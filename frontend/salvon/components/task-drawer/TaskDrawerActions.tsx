import ActivityList from './ActivityList';
import CommentList from './CommentList';
import TaskDrawerSection from './TaskDrawerSection';
import type { TaskDrawerActionsProps } from './types.d';
import { MdOutlineChatBubbleOutline } from 'react-icons/md';

import { Div } from '@salvon/components/div';
import { Tabs } from '@salvon/components/tabs';
import { useTranslation } from '@salvon/hooks/useTranslation';

export default function TaskDrawerActions({
  comments = [],
  activity = [],
  commentsLoading = false,
  activityLoading = false,
  onDeleteComment,
  title,
  icon = <MdOutlineChatBubbleOutline size={15} />,
  defaultOpen = true,
  showActivity = true,
  commentsLabel,
  activityLabel,
  commentsEmptyText,
  activityEmptyText,
  open,
  setOpen,
}: TaskDrawerActionsProps) {
  const t = useTranslation();

  const items = [
    {
      name: 'comments',
      label: commentsLabel ?? t('comments'),
      content: (
        <Div sx={{ pt: 1.5 }}>
          <CommentList
            items={comments}
            loading={commentsLoading}
            emptyText={commentsEmptyText}
            onDelete={onDeleteComment}
          />
        </Div>
      ),
    },
    ...(showActivity
      ? [
          {
            name: 'activity',
            label: activityLabel ?? t('history'),
            content: (
              <Div sx={{ pt: 1.5 }}>
                <ActivityList
                  items={activity}
                  loading={activityLoading}
                  emptyText={activityEmptyText}
                />
              </Div>
            ),
          },
        ]
      : []),
  ];

  return (
    <TaskDrawerSection
      title={title ?? t('actions')}
      icon={icon}
      defaultOpen={defaultOpen}
      open={open}
      setOpen={setOpen}
      slotProps={{ body: { sx: { px: 0.5 } } }}
    >
      <Tabs
        queryParam={false}
        items={items}
        slotProps={{
          container: { sx: { width: '100%' } },
          tabs: {
            variant: 'fullWidth',
            sx: {
              width: '100%',
              minHeight: 36,
              borderBottom: 'none',
              borderRadius: '10px',
              p: '4px',
              gap: '4px',
              backgroundColor: (t) =>
                t.palette.mode === 'dark'
                  ? 'rgba(255,255,255,0.05)'
                  : '#eef1f6',
              border: (t) =>
                `1px solid ${
                  t.palette.mode === 'dark' ? '#242424' : '#e7ebf1'
                }`,
              '& .MuiTabs-indicator': { display: 'none' },
              '& .MuiTab-root': {
                minHeight: 28,
                minWidth: 0,
                textTransform: 'none',
                fontSize: '0.78rem',
                fontWeight: 600,
                letterSpacing: 0,
                borderRadius: '7px',
                px: 1.5,
                py: 0,
                color: 'text.secondary',
                transition: 'background-color 150ms ease, color 150ms ease',
                '&:hover': { color: 'text.primary' },
              },
              '& .MuiTab-root.Mui-selected': {
                color: 'text.primary',
                backgroundColor: (t) =>
                  t.palette.mode === 'dark' ? 'rgba(255,255,255,0.10)' : '#fff',
                boxShadow: (t) =>
                  t.palette.mode === 'dark'
                    ? 'none'
                    : '0 1px 2px rgba(16,24,40,.08), 0 1px 3px rgba(16,24,40,.06)',
              },
            },
          },
        }}
      />
    </TaskDrawerSection>
  );
}
