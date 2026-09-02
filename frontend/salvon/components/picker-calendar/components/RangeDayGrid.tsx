import { alpha } from '@mui/material';

import { rangeCellSx } from './cellSx';
import dayjs, { type Dayjs } from 'dayjs';
import { useMemo } from 'react';

import { Div, Flex } from '@salvon/components/div';
import { usePalette } from '@salvon/hooks/useTheme';

type RangeDayGridProps = {
  visibleMonth: Dayjs;
  start: Dayjs | null;
  end: Dayjs | null;
  hovered: Dayjs | null;
  locale: string;
  minDate?: Dayjs;
  maxDate?: Dayjs;
  /** Render leading/trailing days of adjacent months as empty cells. */
  hideOutsideDays?: boolean;
  onSelectDay: (day: Dayjs) => void;
  onHoverDay: (day: Dayjs | null) => void;
};

const CELL = 34;

export default function RangeDayGrid({
  visibleMonth,
  start,
  end,
  hovered,
  locale,
  minDate,
  maxDate,
  hideOutsideDays,
  onSelectDay,
  onHoverDay,
}: RangeDayGridProps) {
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

  // While picking the end, preview the range up to the hovered day.
  const previewEnd = start && !end ? hovered : end;
  const rangeStart =
    start && previewEnd && previewEnd.isBefore(start) ? previewEnd : start;
  const rangeEnd =
    start && previewEnd && previewEnd.isBefore(start) ? start : previewEnd;

  const today = dayjs();
  const palette = usePalette();
  //@ts-ignore
  const bandBg = alpha(palette?.primary?.main ?? '#3f51b5', 0.1);

  return (
    <Div>
      <Div
        sx={{
          display: 'grid',
          gridTemplateColumns: 'repeat(7, 1fr)',
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
          rowGap: 0.5,
        }}
      >
        {days.map((d, i) => {
          const muted = d.month() !== visibleMonth.month();
          if (muted && hideOutsideDays) {
            return <Div key={d.valueOf()} />;
          }
          const disabled = isDisabled(d);
          const isStart = !!rangeStart && d.isSame(rangeStart, 'day');
          const isEnd = !!rangeEnd && d.isSame(rangeEnd, 'day');
          const endpoint = isStart || isEnd;
          const inRange =
            !!rangeStart &&
            !!rangeEnd &&
            d.isAfter(rangeStart, 'day') &&
            d.isBefore(rangeEnd, 'day');

          const banded = endpoint || inRange;
          const col = i % 7; // 0 = Monday … 6 = Sunday
          // A two-ended range actually exists (endpoints differ).
          const hasSpan =
            !!rangeStart && !!rangeEnd && !rangeStart.isSame(rangeEnd, 'day');
          // Start-of-span rounds its left only (right edge merges into the band);
          // end-of-span rounds its right only. A lone endpoint stays fully round.
          const flatRight = hasSpan && isStart && !isEnd;
          const flatLeft = hasSpan && isEnd && !isStart;
          const roundLeft = banded && !flatLeft && (endpoint || col === 0);
          const roundRight = banded && !flatRight && (endpoint || col === 6);

          return (
            <Flex
              key={d.valueOf()}
              onClick={() => !disabled && onSelectDay(d)}
              onMouseEnter={() => onHoverDay(d)}
              sx={rangeCellSx({
                endpoint,
                inRange,
                muted,
                disabled,
                today: d.isSame(today, 'day'),
                roundLeft,
                roundRight,
                bandBg,
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
