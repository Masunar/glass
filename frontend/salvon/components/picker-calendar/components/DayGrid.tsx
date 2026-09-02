import { cellSx } from './cellSx';
import dayjs, { type Dayjs } from 'dayjs';
import { useMemo } from 'react';

import { Div, Flex } from '@salvon/components/div';

type DayGridProps = {
  visibleMonth: Dayjs;
  value: Dayjs | null;
  locale: string;
  minDate?: Dayjs;
  maxDate?: Dayjs;
  hideOutsideDays?: boolean;
  onSelectDay: (day: Dayjs) => void;
};

const CELL = 34;

export default function DayGrid({
  visibleMonth,
  value,
  locale,
  minDate,
  maxDate,
  hideOutsideDays,
  onSelectDay,
}: DayGridProps) {
  const weekdays = useMemo(() => {
    const monday = dayjs().locale(locale).day(1);
    return Array.from({ length: 7 }, (_, i) =>
      monday.add(i, 'day').format('dd'),
    );
  }, [locale]);

  const days = useMemo(() => {
    const first = visibleMonth.startOf('month');
    const offset = (first.day() + 6) % 7;
    const gridStart = first.subtract(offset, 'day');
    const weeks = Math.ceil((offset + visibleMonth.daysInMonth()) / 7);
    return Array.from({ length: weeks * 7 }, (_, i) => gridStart.add(i, 'day'));
  }, [visibleMonth]);

  const isDisabled = (d: Dayjs) =>
    (minDate && d.isBefore(minDate, 'day')) ||
    (maxDate && d.isAfter(maxDate, 'day'));

  const today = dayjs();

  return (
    <Div>
      <Div
        sx={{
          display: 'grid',
          gridTemplateColumns: 'repeat(7, 1fr)',
          gap: 0.5,
          mb: 0.5,
        }}
      >
        {weekdays.map((w, i) => (
          <Flex
            key={i}
            center
            sx={{
              fontSize: '0.65rem',
              fontWeight: 600,
              textTransform: 'uppercase',
              color: 'text.secondary',
              height: 22,
            }}
          >
            {w}
          </Flex>
        ))}
      </Div>

      <Div
        sx={{
          display: 'grid',
          gridTemplateColumns: 'repeat(7, 1fr)',
          gridAutoRows: `${CELL}px`,
          gap: 0.5,
        }}
      >
        {days.map((d) => {
          const muted = d.month() !== visibleMonth.month();
          if (muted && hideOutsideDays) {
            return <Div key={d.valueOf()} />;
          }
          const selected = !!value && d.isSame(value, 'day');
          const disabled = isDisabled(d);
          return (
            <Flex
              key={d.valueOf()}
              onClick={() => !disabled && onSelectDay(d)}
              sx={cellSx({
                selected,
                muted,
                disabled,
                today: d.isSame(today, 'day'),
              })}
            >
              {d.date()}
            </Flex>
          );
        })}
      </Div>
    </Div>
  );
}
