import {
  type SchedulerAccessors,
  type SchedulerCalendar,
  type SchedulerRange,
  type SchedulerView,
} from './types.d';
import dayjs from 'dayjs';
import { type ReactNode } from 'react';
import { type View as RbcView } from 'react-big-calendar';

export type RbcEvent<T> = {
  id: string;
  title: ReactNode;
  start: Date;
  end: Date;
  allDay: boolean;
  color?: string;
  source: T;
};

const VIEW_TO_RBC: Record<SchedulerView, RbcView> = {
  month: 'month',
  week: 'week',
  day: 'day',
  list: 'agenda',
  day_list: 'agenda',
  year: 'month',
};

const RBC_TO_VIEW: Record<string, SchedulerView> = {
  month: 'month',
  week: 'week',
  day: 'day',
  agenda: 'list',
};

export const toRbcView = (view: SchedulerView): RbcView => VIEW_TO_RBC[view];
export const fromRbcView = (view: RbcView): SchedulerView =>
  RBC_TO_VIEW[view] ?? 'month';

const VIEW_UNIT: Record<SchedulerView, dayjs.ManipulateType> = {
  month: 'month',
  week: 'week',
  day: 'day',
  list: 'month',
  day_list: 'day',
  year: 'year',
};

export const navigateDate = (
  date: Date,
  view: SchedulerView,
  direction: -1 | 1,
): Date => dayjs(date).add(direction, VIEW_UNIT[view]).toDate();

export const formatLabel = (
  date: Date,
  view: SchedulerView,
  locale: string,
): string => {
  const d = dayjs(date).locale(locale);
  switch (view) {
    case 'year':
      return d.format('YYYY');
    case 'day':
    case 'day_list':
      return d.format('D MMMM YYYY');
    case 'week': {
      const start = d.startOf('week');
      const end = d.endOf('week');
      const sameMonth = start.month() === end.month();
      return sameMonth
        ? `${start.format('D')}–${end.format('D MMMM YYYY')}`
        : `${start.format('D MMM')} – ${end.format('D MMM YYYY')}`;
    }
    default:
      return d.format('MMMM YYYY');
  }
};

export const rangeForView = (
  date: Date,
  view: SchedulerView,
): SchedulerRange => {
  const d = dayjs(date);
  switch (view) {
    case 'year':
      return {
        start: d.startOf('year').toDate(),
        end: d.endOf('year').toDate(),
      };
    case 'day':
    case 'day_list':
      return { start: d.startOf('day').toDate(), end: d.endOf('day').toDate() };
    case 'week':
      return {
        start: d.startOf('week').toDate(),
        end: d.endOf('week').toDate(),
      };
    default:
      return {
        start: d.startOf('month').startOf('week').toDate(),
        end: d.endOf('month').endOf('week').toDate(),
      };
  }
};

export const resolveColor = <T>(
  event: T,
  accessors: SchedulerAccessors<T>,
  calendarById: Map<string, SchedulerCalendar>,
): string | undefined => {
  const explicit = accessors.getColor?.(event);
  if (explicit) return explicit;
  const calendarId = accessors.getCalendarId?.(event);
  if (calendarId) return calendarById.get(calendarId)?.color;
  return undefined;
};

export const buildCalendarMap = (
  calendars: SchedulerCalendar[] | undefined,
): Map<string, SchedulerCalendar> =>
  new Map((calendars ?? []).map((c) => [c.id, c]));

export const dayKey = (date: Date): string => dayjs(date).format('YYYY-MM-DD');

export const formatEventTime = <T>(
  event: RbcEvent<T>,
  locale: string,
  allDayLabel: string,
): string => {
  const start = dayjs(event.start).locale(locale);
  const end = dayjs(event.end).locale(locale);
  if (event.allDay) {
    const spanDays = end.startOf('day').diff(start.startOf('day'), 'day');
    if (spanDays >= 1) {
      return start.month() === end.month()
        ? `${start.format('D')} – ${end.format('D MMMM')}`
        : `${start.format('D MMM')} – ${end.format('D MMM')}`;
    }
    return allDayLabel;
  }
  return `${start.format('HH:mm')} – ${end.format('HH:mm')}`;
};

export const eventOnDay = <T>(event: RbcEvent<T>, day: Date): boolean => {
  const start = dayjs(day).startOf('day');
  const end = dayjs(day).endOf('day');
  return dayjs(event.start).isBefore(end) && dayjs(event.end).isAfter(start);
};

export const eventsForDay = <T>(
  events: RbcEvent<T>[],
  day: Date,
): RbcEvent<T>[] =>
  events
    .filter((e) => eventOnDay(e, day))
    .sort((a, b) => {
      if (a.allDay !== b.allDay) return a.allDay ? -1 : 1;
      return a.start.getTime() - b.start.getTime();
    });

export const groupByDay = <T>(
  events: RbcEvent<T>[],
  from: Date,
): { day: Date; events: RbcEvent<T>[] }[] => {
  const start = dayjs(from).startOf('day');
  const map = new Map<string, { day: Date; events: RbcEvent<T>[] }>();
  for (const e of events) {
    if (dayjs(e.end).isBefore(start)) continue;
    const key = dayKey(e.start);
    if (!map.has(key)) map.set(key, { day: e.start, events: [] });
    map.get(key)!.events.push(e);
  }
  return [...map.values()]
    .sort((a, b) => a.day.getTime() - b.day.getTime())
    .map((g) => ({
      ...g,
      events: g.events.sort((a, b) => {
        if (a.allDay !== b.allDay) return a.allDay ? -1 : 1;
        return a.start.getTime() - b.start.getTime();
      }),
    }));
};

export const resolveEvents = <T>(
  events: T[],
  accessors: SchedulerAccessors<T>,
  calendarById: Map<string, SchedulerCalendar>,
  hiddenCalendarIds?: Set<string>,
): RbcEvent<T>[] => {
  const result: RbcEvent<T>[] = [];
  for (const event of events) {
    const calendarId = accessors.getCalendarId?.(event);
    if (calendarId && hiddenCalendarIds?.has(calendarId)) continue;
    result.push({
      id: accessors.getId(event),
      title: accessors.getTitle(event),
      start: accessors.getStart(event),
      end: accessors.getEnd(event),
      allDay: accessors.getAllDay?.(event) ?? false,
      color: resolveColor(event, accessors, calendarById),
      source: event,
    });
  }
  return result;
};
