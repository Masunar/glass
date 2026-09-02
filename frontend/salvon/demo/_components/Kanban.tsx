import { Typography } from '@mui/material';

import { useState } from 'react';
import { MdOutlineAddTask, MdViewKanban } from 'react-icons/md';

import { Card } from '@salvon/components/card';
import { Div, Flex } from '@salvon/components/div';
import { Kanban as SalvonKanban } from '@salvon/components/kanban';
import type { KanbanColumn } from '@salvon/components/kanban';
import KanbanTaskDrawer from '@salvon/demo/_components/KanbanTaskDrawer';

type Task = {
  id: string;
  title: string;
  status: string;
  assignee: string;
};

const columns: KanbanColumn[] = [
  { id: 'todo', label: 'To do', color: '#9ca3af' },
  { id: 'doing', label: 'In progress', color: '#3b82f6' },
  { id: 'done', label: 'Done', color: '#a855f7' },
];

const initialTasks: Task[] = [
  { id: '1', title: 'Lorem ipsum dolor sit', status: 'todo', assignee: 'A' },
  {
    id: '2',
    title: 'Consectetur adipiscing elit',
    status: 'todo',
    assignee: '',
  },
  { id: '3', title: 'Sed do eiusmod tempor', status: 'todo', assignee: '' },
  { id: '4', title: 'Incididunt ut labore', status: 'doing', assignee: 'A' },
];

const statusOf = (id: string) => columns.find((c) => c.id === id)!;

export default function Kanban() {
  const [tasks, setTasks] = useState<Task[]>(initialTasks);

  const handleMove = (itemId: string, toColumn: string) => {
    setTasks((prev) =>
      prev.map((t) => (t.id === itemId ? { ...t, status: toColumn } : t)),
    );
  };

  return (
    <Card
      fw
      heading={{
        icon: <MdViewKanban />,
        title: 'Kanban',
        subtitle: 'Tablica drag & drop — kolumny, karty i podgląd szczegółów',
      }}
    >
      <KanbanTaskDrawer />
      <Flex sx={{ minHeight: 480 }}>
        <SalvonKanban<Task>
          items={tasks}
          columns={columns}
          getItemId={(t) => t.id}
          getColumnId={(t) => t.status}
          onMove={handleMove}
          slotProps={{
            column: {
              sx: {
                borderRadius: 2,
                border: '1px solid',
                borderColor: 'divider',
                overflow: 'hidden',
                backgroundColor: 'background.paper',
              },
            },
            columnBody: { sx: { px: 1, pb: 1 } },
          }}
          renderColumnHeader={(column, count) => (
            <Div sx={{ borderTop: `3px solid ${column.color}` }}>
              <Flex aCenter sx={{ gap: 1, px: 1.5, py: 1.25 }}>
                <StatusDot color={column.color} />
                <Typography
                  sx={{
                    fontSize: 12,
                    fontWeight: 700,
                    letterSpacing: 0.5,
                    textTransform: 'uppercase',
                    color: 'text.secondary',
                  }}
                >
                  {column.label}
                </Typography>
                <Typography
                  sx={{ ml: 'auto', fontSize: 13, color: 'text.disabled' }}
                >
                  {count}
                </Typography>
              </Flex>
            </Div>
          )}
          renderColumnBottom={(column) => (
            <Flex
              aCenter
              sx={{
                gap: 1,
                px: 1,
                py: 1,
                color: 'text.disabled',
                cursor: 'pointer',
                fontSize: 14,
                '&:hover': { color: 'text.secondary' },
              }}
              onClick={() => console.log('create in', column.id)}
            >
              <MdOutlineAddTask size={18} />
              Utwórz zadanie
            </Flex>
          )}
          renderColumnTop={() => <Div mt={1} />}
          renderTaskCard={(task) => {
            const status = statusOf(task.status);
            return (
              <Div
                sx={{
                  p: 1.5,
                  borderRadius: 2,
                  border: '1px solid',
                  borderColor: 'divider',
                  backgroundColor: 'background.paper',
                  cursor: 'pointer',
                }}
              >
                <Typography variant="body2" sx={{ fontWeight: 600, mb: 1.5 }}>
                  {task.title}
                </Typography>
                <Flex aCenter sx={{ gap: 1 }}>
                  <StatusDot color={status.color} />
                  <Typography
                    sx={{
                      fontSize: 11,
                      fontWeight: 700,
                      letterSpacing: 0.5,
                      textTransform: 'uppercase',
                      color: 'text.secondary',
                    }}
                  >
                    {status.label}
                  </Typography>
                  <Avatar initial={task.assignee} />
                </Flex>
              </Div>
            );
          }}
          renderTaskDetails={(task, close) => (
            <Flex
              onClick={close}
              center
              sx={{
                position: 'fixed',
                inset: 0,
                zIndex: 1300,
                backgroundColor: 'rgba(0,0,0,0.4)',
              }}
            >
              <Card
                heading={{
                  title: task.title,
                  subtitle: statusOf(task.status).label,
                }}
                sx={{ minWidth: 320 }}
              >
                <Typography variant="body2">Status: {task.status}</Typography>
              </Card>
            </Flex>
          )}
        />
      </Flex>
    </Card>
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

function Avatar({ initial }: { initial: string }) {
  return (
    <Flex
      center
      sx={{
        ml: 'auto',
        width: 24,
        height: 24,
        borderRadius: '50%',
        backgroundColor: 'action.hover',
        fontSize: 12,
        color: 'text.secondary',
        flexShrink: 0,
      }}
    >
      {initial || '–'}
    </Flex>
  );
}
