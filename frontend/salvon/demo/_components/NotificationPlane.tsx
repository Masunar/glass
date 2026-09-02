import { useState } from 'react';
import { MdOutlineNotifications } from 'react-icons/md';
import { PiChatCircle, PiFileText, PiWarningCircle } from 'react-icons/pi';

import { Card } from '@salvon/components/card';
import { Flex } from '@salvon/components/div';
import { NotificationsPlane } from '@salvon/components/notifications-plane';
import type { NotificationItem } from '@salvon/components/notifications-plane';

const initial: NotificationItem[] = [
  {
    id: '1',
    title: 'Nowy komentarz do zadania',
    description: 'Anna K. skomentowała „Makieta widoku listy”.',
    date: new Date().toISOString(),
    icon: <PiChatCircle size={18} />,
    color: { bg: '#e0efff', fg: '#2563eb' },
    onOpen: () => console.log('goto task'),
  },
  {
    id: '2',
    title: 'Umowa oczekuje na akceptację',
    description: 'Umowa #2026/08/14 wymaga Twojej decyzji.',
    date: new Date(Date.now() - 86400000).toISOString(),
    icon: <PiFileText size={18} />,
    color: { bg: '#fef3c7', fg: '#d97706' },
    actions: [{ label: 'Akceptuj', onClick: () => console.log('accept') }],
    onOpen: () => console.log('goto contract'),
  },
  {
    id: '3',
    title: 'Ostrzeżenie o limicie',
    description: 'Wykorzystano 90% limitu produkcji.',
    date: '2026-08-11 09:12:00',
    icon: <PiWarningCircle size={18} />,
    color: { bg: '#fee2e2', fg: '#dc2626' },
    read: true,
  },
];

export default function NotificationPlaneDemo() {
  const [items, setItems] = useState<NotificationItem[]>(initial);

  const markRead = (id: string) =>
    setItems((prev) =>
      prev.map((i) => (i.id === id ? { ...i, read: true } : i)),
    );

  const markAllRead = () =>
    setItems((prev) => prev.map((i) => ({ ...i, read: true })));

  return (
    <Card
      fw
      heading={{
        icon: <MdOutlineNotifications />,
        title: 'Notification plane',
        subtitle: 'Panel powiadomień — badge, popover, sekcje nowe/przeczytane',
      }}
    >
      <Flex sx={{ px: 1, py: 0.5 }}>
        <NotificationsPlane
          items={items}
          onMarkRead={markRead}
          onMarkAllRead={markAllRead}
          onRefresh={() => console.log('refresh')}
          onItemClick={(item) => console.log('open', item.id)}
          panelPlacement="bottom-left"
          labels={{
            title: 'Powiadomienia',
            newSection: 'Nowe',
            readSection: 'Przeczytane',
            noNew: 'Brak nowych powiadomień',
            markAllRead: 'Oznacz wszystkie jako przeczytane',
            markedRead: 'Oznaczono jako przeczytane',
            markReadHint: 'Kliknij, aby oznaczyć jako przeczytane',
            goto: 'Przejdź →',
            today: 'Dziś',
            yesterday: 'Wczoraj',
            summary: (n) => `${n} nieprzeczytanych`,
          }}
        />
      </Flex>
    </Card>
  );
}
