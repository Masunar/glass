import type { CalendarView, DateRange } from '../types.d';
import { YEARS_PER_PAGE } from '../useCalendarState';
import CalendarHeader from './CalendarHeader';
import MonthGrid from './MonthGrid';
import RangeDayGrid from './RangeDayGrid';
import YearGrid from './YearGrid';
import type { Dayjs } from 'dayjs';
import { useState } from 'react';

import { Div } from '@salvon/components/div';

type RangeMonthPanelProps = {
  visibleMonth: Dayjs;
  range: DateRange;
  hovered: Dayjs | null;
  locale: string;
  arrows: 'both' | 'left' | 'right';
  /** Enabled calendar views, finest-last. The finest is where a pick commits. */
  activeViews?: CalendarView[];
  minDate?: Dayjs;
  maxDate?: Dayjs;
  hideOutsideDays?: boolean;
  onVisibleMonthChange: (month: Dayjs) => void;
  onSelectDay: (day: Dayjs) => void;
  onHoverDay: (day: Dayjs | null) => void;
};

const ALL_VIEWS: CalendarView[] = ['year', 'month', 'day'];

export default function RangeMonthPanel({
  visibleMonth,
  range,
  hovered,
  locale,
  arrows,
  activeViews = ALL_VIEWS,
  minDate,
  maxDate,
  hideOutsideDays,
  onVisibleMonthChange,
  onSelectDay,
  onHoverDay,
}: RangeMonthPanelProps) {
  const minView = activeViews[activeViews.length - 1];
  const hasView = (v: CalendarView) => activeViews.includes(v);
  const [view, setView] = useState<CalendarView>(minView);
  const yearRangeStart =
    visibleMonth.year() - (visibleMonth.year() % YEARS_PER_PAGE);

  return (
    <Div sx={{ minWidth: { xs: 0, sm: 250 } }}>
      <CalendarHeader
        view={view}
        visibleMonth={visibleMonth}
        yearRangeStart={yearRangeStart}
        locale={locale}
        canOpenMonth={hasView('month')}
        canOpenYear={hasView('year')}
        arrows={arrows}
        onStep={(m) => onVisibleMonthChange(visibleMonth.add(m, 'month'))}
        onStepYears={(y) => onVisibleMonthChange(visibleMonth.add(y, 'year'))}
        onOpenMonthView={() => setView('month')}
        onOpenYearView={() => setView('year')}
      />

      {view === 'day' && (
        <RangeDayGrid
          visibleMonth={visibleMonth}
          start={range.start}
          end={range.end}
          hovered={hovered}
          locale={locale}
          minDate={minDate}
          maxDate={maxDate}
          hideOutsideDays={hideOutsideDays}
          onSelectDay={onSelectDay}
          onHoverDay={onHoverDay}
        />
      )}
      {view === 'month' && (
        <MonthGrid
          visibleMonth={visibleMonth}
          value={range.start}
          locale={locale}
          onSelectMonth={(m) => {
            const month = visibleMonth.month(m);
            onVisibleMonthChange(month);
            if (hasView('day')) {
              setView('day');
            } else {
              onSelectDay(month.startOf('month'));
            }
          }}
        />
      )}
      {view === 'year' && (
        <YearGrid
          yearRangeStart={yearRangeStart}
          visibleMonth={visibleMonth}
          value={range.start}
          onSelectYear={(y) => {
            const yr = visibleMonth.year(y);
            onVisibleMonthChange(yr);
            if (hasView('month')) {
              setView('month');
            } else if (hasView('day')) {
              setView('day');
            } else {
              onSelectDay(yr.startOf('year'));
            }
          }}
        />
      )}
    </Div>
  );
}
