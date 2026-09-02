import type { CalendarView } from './types.d';
import dayjs, { type Dayjs } from 'dayjs';
import { useEffect, useMemo, useRef, useState } from 'react';

export const YEARS_PER_PAGE = 16;

type UseCalendarStateArgs = {
  value: Dayjs | null;
  initialView: CalendarView;
};

export function useCalendarState({ value, initialView }: UseCalendarStateArgs) {
  const [view, setView] = useState<CalendarView>(initialView);
  const [visibleMonth, setVisibleMonth] = useState<Dayjs>(() =>
    (value ?? dayjs()).startOf('month'),
  );

  const lastValueMonth = useRef(value?.format('YYYY-MM'));
  useEffect(() => {
    const key = value?.format('YYYY-MM');
    if (value && key !== lastValueMonth.current) {
      setVisibleMonth(value.startOf('month'));
    }
    lastValueMonth.current = key;
  }, [value]);

  const yearRangeStart = useMemo(() => {
    const y = visibleMonth.year();
    return y - (y % YEARS_PER_PAGE);
  }, [visibleMonth]);

  return {
    view,
    setView,
    visibleMonth,
    setVisibleMonth,
    yearRangeStart,
  };
}
