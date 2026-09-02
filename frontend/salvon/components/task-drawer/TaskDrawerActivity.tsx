import ActivityList from './ActivityList';
import TaskDrawerSection from './TaskDrawerSection';
import type { TaskDrawerActivityProps } from './types.d';
import { MdHistory } from 'react-icons/md';

import { useTranslation } from '@salvon/hooks/useTranslation';

export default function TaskDrawerActivity({
  items = [],
  loading = false,
  title,
  icon = <MdHistory size={15} />,
  defaultOpen = false,
  emptyText,
  open,
  setOpen,
}: TaskDrawerActivityProps) {
  const t = useTranslation();

  return (
    <TaskDrawerSection
      title={title ?? t('history')}
      icon={icon}
      defaultOpen={defaultOpen}
      open={open}
      setOpen={setOpen}
      loading={loading}
    >
      <ActivityList items={items} loading={loading} emptyText={emptyText} />
    </TaskDrawerSection>
  );
}
