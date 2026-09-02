import { type RbcEvent, buildCalendarMap, resolveEvents } from './internal';
import { type SchedulerBaseProps } from './types.d';
import { useHiddenCalendars } from './useSchedulerCalendars';
import dayjs from 'dayjs';
import { useEffect, useMemo, useRef, useState } from 'react';

export type SchedulerData<T> = {
  resolved: RbcEvent<T>[];
  hiddenSet: Set<string>;
  toggleCalendar: (id: string) => void;
  date: Date;
  setDate: (date: Date) => void;
};

export const useSchedulerData = <T>(
  props: SchedulerBaseProps<T>,
): SchedulerData<T> => {
  const { hiddenSet, toggle } = useHiddenCalendars(props);

  const calendarById = useMemo(
    () => buildCalendarMap(props.calendars),
    [props.calendars],
  );

  const resolved = useMemo(
    () =>
      resolveEvents(
        props.events,
        {
          getId: props.getId,
          getTitle: props.getTitle,
          getStart: props.getStart,
          getEnd: props.getEnd,
          getAllDay: props.getAllDay,
          getCalendarId: props.getCalendarId,
          getColor: props.getColor,
        },
        calendarById,
        hiddenSet,
      ),
    [
      props.events,
      calendarById,
      hiddenSet,
      props.getId,
      props.getTitle,
      props.getStart,
      props.getEnd,
      props.getAllDay,
      props.getCalendarId,
      props.getColor,
    ],
  );

  const [internalDate, setInternalDate] = useState<Date>(
    props.defaultDate ?? new Date(),
  );
  const date = props.date ?? internalDate;
  const setDate = (next: Date) => {
    props.onNavigate?.(next, 'list');
    if (props.date === undefined) setInternalDate(next);
  };

  const onRangeRef = useRef(props.onRangeChange);
  onRangeRef.current = props.onRangeChange;
  useEffect(() => {
    const start = dayjs(date).startOf('month').startOf('week').toDate();
    const end = dayjs(date).endOf('month').endOf('week').toDate();
    onRangeRef.current?.({ start, end }, 'list');
  }, [date]);

  return { resolved, hiddenSet, toggleCalendar: toggle, date, setDate };
};
