import CalendarFooter from './components/CalendarFooter';
import CalendarHeader from './components/CalendarHeader';
import DayGrid from './components/DayGrid';
import MonthGrid from './components/MonthGrid';
import TimeStepInput from './components/TimeStepInput';
import YearGrid from './components/YearGrid';
import type {
  CalendarView,
  PickerCalendarProps,
  PickerCalendarView,
  QuickAction,
  TimeView,
} from './types.d';
import { useCalendarState } from './useCalendarState';
import dayjs, { type Dayjs } from 'dayjs';
import { useMemo, useState } from 'react';

import { Div, Flex } from '@salvon/components/div';
import { useCurrentLocale } from '@salvon/hooks/useLocale';
import { usePalette } from '@salvon/hooks/useTheme';

const CAL_ORDER: CalendarView[] = ['year', 'month', 'day'];
const TIME_ORDER: TimeView[] = ['hours', 'minutes', 'seconds'];

export default function PickerCalendar({
  mode = 'date',
  fw = false,
  value,
  defaultValue = null,
  onChange,
  onApply,
  onClear,
  minDate,
  maxDate,
  locale: localeProp,
  format,
  hourCycle = 24,
  views,
  openTo,
  hideFooter = false,
  hideOutsideDays,
  quickActions,
  renderFooter,
  labels,
  slotProps,
}: PickerCalendarProps) {
  const is12h = hourCycle === 12;
  const palette = usePalette();
  const appLocale = useCurrentLocale();
  const locale = localeProp ?? appLocale;

  const enabledViews = useMemo(() => {
    if (views) {
      return new Set(views);
    }
    if (mode === 'date') {
      return new Set<PickerCalendarView>(CAL_ORDER);
    }
    if (mode === 'time') {
      return new Set<PickerCalendarView>(TIME_ORDER);
    }
    return new Set<PickerCalendarView>([...CAL_ORDER, ...TIME_ORDER]);
  }, [views, mode]);

  const activeViews = CAL_ORDER.filter((v) => enabledViews.has(v));
  const timeViews = TIME_ORDER.filter((v) => enabledViews.has(v));

  const minView = activeViews[activeViews.length - 1];
  const hasView = (v: CalendarView) => activeViews.includes(v);

  const [internalValue, setInternalValue] = useState<Dayjs | null>(
    defaultValue,
  );
  const current = value !== undefined ? value : internalValue;
  const base = useMemo(() => current ?? dayjs().startOf('day'), [current]);

  const { view, setView, visibleMonth, setVisibleMonth, yearRangeStart } =
    useCalendarState({
      value: current,
      initialView: openTo ?? minView ?? 'day',
    });

  const showCalendar = activeViews.length > 0;
  const showTime = timeViews.length > 0;

  function formatValue(v: Dayjs | null): string {
    if (!v) {
      return '';
    }
    if (format) {
      return v.locale(locale).format(format);
    }

    const parts: string[] = [];
    if (timeViews.includes('hours')) {
      parts.push(is12h ? 'hh' : 'HH');
    }
    if (timeViews.includes('minutes')) {
      parts.push('mm');
    }
    if (timeViews.includes('seconds')) {
      parts.push('ss');
    }
    let timeFmt = parts.join(':');
    if (timeFmt && is12h && timeViews.includes('hours')) {
      timeFmt += ' A';
    }
    const timePart = timeFmt ? v.format(timeFmt) : '';

    if (activeViews.length === 0) {
      return timePart;
    }

    const dateFmt =
      minView === 'year'
        ? 'YYYY'
        : minView === 'month'
          ? 'MM/YYYY'
          : 'DD/MM/YYYY';
    const datePart = v.format(dateFmt);

    return timePart ? `${datePart} ${timePart}` : datePart;
  }

  function update(next: Dayjs | null) {
    onChange?.(next, formatValue(next));
    if (value === undefined) {
      setInternalValue(next);
    }
  }

  function selectDay(day: Dayjs) {
    setVisibleMonth(day.startOf('month'));
    update(day.hour(base.hour()).minute(base.minute()).second(base.second()));
  }

  function selectMonth(monthIndex: number) {
    const month = visibleMonth.month(monthIndex);
    setVisibleMonth(month);
    if (hasView('day')) {
      setView('day');
      return;
    }
    update(month.date(Math.min(base.date(), month.daysInMonth())));
  }

  function selectYear(year: number) {
    setVisibleMonth(visibleMonth.year(year));
    if (hasView('month')) {
      setView('month');
      return;
    }
    if (hasView('day')) {
      setView('day');
      return;
    }
    update(base.year(year));
  }

  const stepMonths = (m: number) =>
    setVisibleMonth(visibleMonth.add(m, 'month'));
  const stepYears = (y: number) => setVisibleMonth(visibleMonth.add(y, 'year'));

  const summary = formatValue(current);

  function handleQuickAction(a: QuickAction) {
    const next = a.value(current);
    setVisibleMonth(next.startOf('month'));
    update(next);
  }

  const paper = {
    p: 1.5,
    borderRadius: '14px',
    border: '1px solid',
    borderColor: 'divider',
    backgroundColor: 'background.paper',
    ...(fw
      ? { display: 'block', width: '100%' }
      : { display: 'inline-block', maxWidth: '100%' }),
    ...((palette?.salvon?.popover?.paper ?? { boxShadow: 3 }) as any),
  };

  return (
    <Div
      {...slotProps?.root}
      sx={{ ...paper, maxWidth: '340px', ...(slotProps?.root?.sx ?? {}) }}
    >
      <Flex
        gap={2}
        wrap
        sx={{ alignItems: 'stretch', justifyContent: 'center' }}
      >
        {showCalendar && (
          <Div
            sx={{
              width: 'min(285px, 100%)',
              minWidth: 285,
            }}
          >
            <CalendarHeader
              view={view}
              visibleMonth={visibleMonth}
              yearRangeStart={yearRangeStart}
              locale={locale}
              canOpenMonth={hasView('month')}
              canOpenYear={hasView('year')}
              onStep={stepMonths}
              onStepYears={stepYears}
              onOpenMonthView={() => setView('month')}
              onOpenYearView={() => setView('year')}
            />

            {view === 'day' && (
              <DayGrid
                visibleMonth={visibleMonth}
                value={current}
                locale={locale}
                minDate={minDate}
                maxDate={maxDate}
                hideOutsideDays={hideOutsideDays}
                onSelectDay={selectDay}
              />
            )}
            {view === 'month' && (
              <MonthGrid
                visibleMonth={visibleMonth}
                value={current}
                locale={locale}
                onSelectMonth={selectMonth}
              />
            )}
            {view === 'year' && (
              <YearGrid
                yearRangeStart={yearRangeStart}
                visibleMonth={visibleMonth}
                value={current}
                onSelectYear={selectYear}
              />
            )}
          </Div>
        )}

        {showTime && !showCalendar && (
          <Flex fw center sx={{ px: 1 }}>
            <TimeStepInput
              value={base}
              timeViews={timeViews}
              is12h={is12h}
              onChange={update}
            />
          </Flex>
        )}
      </Flex>

      {showTime && showCalendar && (
        <Flex
          aCenter
          gap={1}
          sx={{
            pt: 1.5,
            mt: 1.5,
            borderTop: '1px solid',
            borderColor: 'divider',
          }}
        >
          <Div
            sx={{
              fontSize: '0.7rem',
              fontWeight: 700,
              letterSpacing: '0.06em',
              textTransform: 'uppercase',
              color: 'text.secondary',
            }}
          >
            {labels?.time ?? ''}
          </Div>
          <TimeStepInput
            value={base}
            timeViews={timeViews}
            is12h={is12h}
            onChange={update}
          />
        </Flex>
      )}

      {!hideFooter &&
        (renderFooter ? (
          current ? (
            renderFooter({
              value: current,
              formattedValue: summary,
              setValue: update,
              apply: (next) => {
                const applied = next === undefined ? current : next;
                if (next !== undefined) {
                  update(next);
                }
                onApply?.(applied);
              },
              clear: () => {
                update(null);
                onClear?.();
              },
            })
          ) : null
        ) : (
          <CalendarFooter
            summary={summary}
            value={current}
            quickActions={showCalendar ? quickActions : undefined}
            clearLabel={labels?.clear ?? 'Wyczyść'}
            applyLabel={labels?.apply ?? 'Zastosuj'}
            onQuickAction={handleQuickAction}
            onClear={() => {
              update(null);
              onClear?.();
            }}
            onApply={() => onApply?.(current)}
          />
        ))}
    </Div>
  );
}
