import dayjs from 'dayjs';
import { useState } from 'react';
import { MdCalendarMonth } from 'react-icons/md';

import { Card } from '@salvon/components/card';
import { Div } from '@salvon/components/div';
import { ApiScheduler, Scheduler } from '@salvon/components/scheduler';
import type {
  SchedulerCalendar,
  SchedulerRange,
} from '@salvon/components/scheduler';
import { Tabs } from '@salvon/components/tabs';

type CalEvent = {
  id: string;
  title: string;
  start: Date;
  end: Date;
  allDay?: boolean;
  calendarId: string;
};

const calendars: SchedulerCalendar[] = [
  { id: 'team', label: 'Zespół', color: '#2563eb' },
  { id: 'releases', label: 'Wydania', color: '#dc2626' },
  { id: 'leave', label: 'Urlopy', color: '#16a34a' },
  { id: 'workshops', label: 'Warsztaty', color: '#0d9488' },
  { id: 'events', label: 'Wydarzenia', color: '#9333ea' },
];

const m = dayjs().startOf('month');
const day = (n: number, h = 9, min = 0) =>
  m
    .add(n - 1, 'day')
    .hour(h)
    .minute(min)
    .toDate();

const initialEvents: CalEvent[] = [
  {
    id: '1',
    title: 'Onboarding klienta Acme',
    calendarId: 'team',
    start: day(2, 0),
    end: day(4, 0),
    allDay: true,
  },
  {
    id: '2',
    title: 'Audyty bezpieczeństwa',
    calendarId: 'events',
    start: day(4, 0),
    end: day(6, 0),
    allDay: true,
  },
  {
    id: '3',
    title: 'Wdrożenie v2.4',
    calendarId: 'team',
    start: day(6, 0),
    end: day(11, 0),
    allDay: true,
  },
  {
    id: '4',
    title: 'Warsztat UX',
    calendarId: 'workshops',
    start: day(7, 15),
    end: day(7, 16),
  },
  {
    id: '5',
    title: 'Retrospektywa',
    calendarId: 'leave',
    start: day(10, 0),
    end: day(13, 0),
    allDay: true,
  },
  {
    id: '6',
    title: 'Przegląd budżetu',
    calendarId: 'releases',
    start: day(13, 10),
    end: day(13, 11),
  },
  {
    id: '7',
    title: 'Konferencja Synteco',
    calendarId: 'events',
    start: day(15, 0),
    end: day(18, 0),
    allDay: true,
  },
  {
    id: '8',
    title: 'Spotkanie zarządu',
    calendarId: 'team',
    start: day(16, 13),
    end: day(16, 14, 30),
  },
  {
    id: '9',
    title: 'Daily standup',
    calendarId: 'team',
    start: day(16, 9),
    end: day(16, 9, 30),
  },
  {
    id: '10',
    title: 'Deploy produkcyjny',
    calendarId: 'releases',
    start: day(17, 17),
    end: day(17, 18),
  },
  {
    id: '11',
    title: 'Urlop — A. Nowak',
    calendarId: 'leave',
    start: day(20, 0),
    end: day(25, 0),
    allDay: true,
  },
  {
    id: '12',
    title: 'Planowanie Q3',
    calendarId: 'team',
    start: day(21, 11),
    end: day(21, 13),
  },
  {
    id: '13',
    title: 'Sync marketing',
    calendarId: 'team',
    start: day(16, 10),
    end: day(16, 10, 30),
  },
  {
    id: '14',
    title: 'Wywiad kandydat',
    calendarId: 'workshops',
    start: day(16, 11),
    end: day(16, 12),
  },
  {
    id: '15',
    title: 'Release notes',
    calendarId: 'releases',
    start: day(16, 15),
    end: day(16, 16),
  },
  {
    id: '16',
    title: 'Demo produktu',
    calendarId: 'events',
    start: day(16, 16, 30),
    end: day(16, 17, 30),
  },
];

export default function SchedulerDemo() {
  const [events, setEvents] = useState<CalEvent[]>(initialEvents);

  const common = {
    calendars,
    getId: (e: CalEvent) => e.id,
    getTitle: (e: CalEvent) => e.title,
    getStart: (e: CalEvent) => e.start,
    getEnd: (e: CalEvent) => e.end,
    getAllDay: (e: CalEvent) => !!e.allDay,
    getCalendarId: (e: CalEvent) => e.calendarId,
    onSelectEvent: (e: CalEvent) => console.log('select event', e.id),
    onSelectSlot: ({
      start,
      end,
      allDay,
    }: {
      start: Date;
      end: Date;
      allDay: boolean;
    }) => {
      const title = window.prompt('Tytuł zdarzenia?');
      if (!title) return;
      setEvents((prev) => [
        ...prev,
        {
          id: `new-${Date.now()}`,
          title,
          start,
          end,
          allDay,
          calendarId: 'team',
        },
      ]);
    },
  };

  const fakeFetch = (range: SchedulerRange): Promise<CalEvent[]> =>
    new Promise((resolve) =>
      setTimeout(
        () =>
          resolve(
            initialEvents.filter(
              (e) => e.start < range.end && e.end > range.start,
            ),
          ),
        600,
      ),
    );

  return (
    <Card
      fw
      heading={{
        icon: <MdCalendarMonth />,
        title: 'Kalendarz',
        subtitle: 'Harmonogram zespołu — miesiąc, tydzień, dzień, agenda',
      }}
    >
      <Tabs
        items={[
          {
            name: 'calendar',
            label: 'Kalendarz',
            content: (
              <Div fw pt={2}>
                <Scheduler<CalEvent>
                  {...common}
                  events={events}
                  variant="calendar"
                  views={['year', 'month', 'week', 'day', 'list', 'day_list']}
                  defaultView="month"
                  height={700}
                  editable
                  selectable
                  onEventChange={({ event, start, end, allDay }) =>
                    setEvents((prev) =>
                      prev.map((e) =>
                        e.id === event.id ? { ...e, start, end, allDay } : e,
                      ),
                    )
                  }
                />
              </Div>
            ),
          },
          {
            name: 'split',
            label: 'Split',
            content: (
              <Div fw pt={2}>
                <Scheduler<CalEvent>
                  {...common}
                  events={events}
                  variant="split"
                  height={560}
                />
              </Div>
            ),
          },
          {
            name: 'compact',
            label: 'Kompakt',
            content: (
              <Div fw pt={2}>
                <Scheduler<CalEvent>
                  {...common}
                  events={events}
                  variant="compact"
                />
              </Div>
            ),
          },
          {
            name: 'api',
            label: 'API Kalendarz',
            content: (
              <Div fw pt={2}>
                <ApiScheduler<CalEvent>
                  {...common}
                  fetcher={fakeFetch}
                  variant="calendar"
                  defaultView="month"
                  height={700}
                />
              </Div>
            ),
          },
          {
            name: 'api-split',
            label: 'API Split',
            content: (
              <Div fw pt={2}>
                <ApiScheduler<CalEvent>
                  {...common}
                  fetcher={fakeFetch}
                  variant="split"
                  height={560}
                />
              </Div>
            ),
          },
          {
            name: 'api-compact',
            label: 'API Kompakt',
            content: (
              <Div fw pt={2}>
                <ApiScheduler<CalEvent>
                  {...common}
                  fetcher={fakeFetch}
                  variant="compact"
                />
              </Div>
            ),
          },
        ]}
      />
    </Card>
  );
}
