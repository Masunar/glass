import { YEARS_PER_PAGE } from '../useCalendarState';
import { cellSx } from './cellSx';
import type { Dayjs } from 'dayjs';

import { Div, Flex } from '@salvon/components/div';

type YearGridProps = {
  yearRangeStart: number;
  value: Dayjs | null;
  visibleMonth: Dayjs;
  onSelectYear: (year: number) => void;
};

export default function YearGrid({
  yearRangeStart,
  value,
  visibleMonth,
  onSelectYear,
}: YearGridProps) {
  const years = Array.from(
    { length: YEARS_PER_PAGE },
    (_, i) => yearRangeStart + i,
  );

  return (
    <Div
      sx={{
        display: 'grid',
        gridTemplateColumns: 'repeat(4, 1fr)',
        gridAutoRows: 42,
        gap: 0.75,
      }}
    >
      {years.map((y) => {
        const selected = !!value && value.year() === y;
        return (
          <Flex
            key={y}
            onClick={() => onSelectYear(y)}
            sx={cellSx({
              selected,
              today: !selected && visibleMonth.year() === y,
            })}
          >
            {y}
          </Flex>
        );
      })}
    </Div>
  );
}
