import { Typography } from '@mui/material';

import dayjs, { type Dayjs } from 'dayjs';
import { useState } from 'react';
import { MdOutlineTaskAlt } from 'react-icons/md';
import {
  PiCalendarBlank,
  PiCalendarPlus,
  PiFlag,
  PiUserCircle,
} from 'react-icons/pi';

import { Button } from '@salvon/components/button';
import { Div, Flex } from '@salvon/components/div';
import {
  TaskDrawer,
  TaskDrawerActions,
  type TaskDrawerActivityEntry,
  type TaskDrawerComment,
  TaskDrawerDescription,
  type TaskDrawerField,
  TaskDrawerMessageBox,
} from '@salvon/components/task-drawer';

type DrawerTask = {
  title: string;
  assignee: string;
  deadline: string | null;
  priority: 'low' | 'medium' | 'high';
  description: string;
  createdAt: string;
  createdBy: string;
};

const initialDrawerTask: DrawerTask = {
  title: 'Przygotować makietę widoku listy',
  assignee: 'A',
  deadline: '2026-09-01 12:00:00',
  priority: 'high',
  description: 'Widok listy z filtrami i paginacją.',
  createdAt: '2026-08-10 09:30:00',
  createdBy: 'Michał R.',
};

const priorityMeta = {
  low: { label: 'Niski', color: '#9ca3af' },
  medium: { label: 'Średni', color: '#f59e0b' },
  high: { label: 'Wysoki', color: '#ef4444' },
} as const;

export default function KanbanTaskDrawer() {
  const [open, setOpen] = useState(false);
  const [drawerTask, setDrawerTask] = useState<DrawerTask>(initialDrawerTask);
  const [comments, setComments] = useState<TaskDrawerComment[]>([
    {
      id: 'c1',
      author: 'Anna K.',
      date: '2026-08-12 10:15:00',
      content: 'Zaczynam od widoku listy.',
    },
  ]);
  const [activity] = useState<TaskDrawerActivityEntry[]>([
    {
      id: 'a1',
      actor: 'Michał R.',
      date: '2026-08-10 09:30:00',
      description: 'Utworzył zadanie.',
    },
    {
      id: 'a2',
      actor: 'Anna K.',
      date: '2026-08-11 14:05:00',
      description: 'Zmieniła priorytet na „Wysoki”.',
    },
  ]);

  const patch = (changes: Partial<DrawerTask>) => {
    setDrawerTask((prev) => ({ ...prev, ...changes }));
    return true;
  };

  const drawerFields: TaskDrawerField[] = [
    {
      key: 'assignee',
      label: 'Przypisany',
      icon: <PiUserCircle size={15} />,
      type: 'select',
      value: drawerTask.assignee,
      options: [
        { value: '', label: 'Nieprzypisany' },
        { value: 'A', label: 'Anna K.' },
        { value: 'M', label: 'Michał R.' },
      ],
      onSave: (value) => patch({ assignee: String(value) }),
    },
    {
      key: 'deadline',
      label: 'Termin',
      icon: <PiCalendarBlank size={15} />,
      type: 'datetime',
      value: drawerTask.deadline ? dayjs(drawerTask.deadline) : null,
      onSave: (value: Dayjs | null) =>
        patch({ deadline: value ? value.format('YYYY-MM-DD HH:mm:ss') : null }),
    },
    {
      key: 'priority',
      label: 'Priorytet',
      icon: <PiFlag size={15} />,
      type: 'select',
      value: drawerTask.priority,
      options: (
        Object.keys(priorityMeta) as Array<keyof typeof priorityMeta>
      ).map((k) => ({ value: k, label: priorityMeta[k].label })),
      render: (value) => (
        <PriorityChip value={value as DrawerTask['priority']} />
      ),
      onSave: (value) => patch({ priority: value as DrawerTask['priority'] }),
    },
    {
      key: 'createdAt',
      label: 'Utworzono',
      icon: <PiCalendarPlus size={15} />,
      type: 'static',
      value: drawerTask.createdAt,
      render: (value) => dayjs(value as string).format('DD.MM.YYYY HH:mm'),
    },
    {
      key: 'createdBy',
      label: 'Autor',
      icon: <PiUserCircle size={15} />,
      type: 'static',
      value: drawerTask.createdBy,
    },
  ];

  return (
    <>
      <Button onClick={() => setOpen(true)}>Otwórz Task Drawer</Button>

      <TaskDrawer
        open={open}
        setOpen={setOpen}
        titleIcon={<MdOutlineTaskAlt size={18} />}
        title={{
          title: drawerTask.title,
          subtitle: 'Zadanie',
          onSave: (value) => value.trim().length > 0 && patch({ title: value }),
        }}
        fields={drawerFields}
        footer={
          <TaskDrawerMessageBox
            authorInitial="T"
            placeholder="Napisz komentarz…"
            onSend={(value) => {
              setComments((prev) => [
                ...prev,
                {
                  id: String(Date.now()),
                  author: 'Ty',
                  date: dayjs().format('YYYY-MM-DD HH:mm:ss'),
                  content: value,
                },
              ]);
              return true;
            }}
          />
        }
      >
        <TaskDrawerDescription
          value={drawerTask.description}
          title="Opis"
          placeholder="Dodaj opis…"
          onSave={(value) => patch({ description: value })}
        />
        <TaskDrawerActions
          title="Aktywność"
          comments={comments}
          activity={activity}
          commentsLabel="Komentarze"
          activityLabel="Historia"
          onDeleteComment={(id) =>
            setComments((prev) => prev.filter((c) => c.id !== id))
          }
        />
      </TaskDrawer>
    </>
  );
}

function PriorityChip({ value }: { value: DrawerTask['priority'] }) {
  const { label, color } = priorityMeta[value];
  return (
    <Flex aCenter sx={{ gap: 0.75 }}>
      <StatusDot color={color} />
      <Typography sx={{ fontSize: 14 }}>{label}</Typography>
    </Flex>
  );
}

function StatusDot({ color }: { color?: string }) {
  return (
    <Div
      sx={{
        width: 8,
        height: 8,
        borderRadius: '50%',
        backgroundColor: color ?? 'text.disabled',
        flexShrink: 0,
      }}
    />
  );
}
