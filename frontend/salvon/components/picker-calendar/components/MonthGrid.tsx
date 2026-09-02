import { cellSx } from './cellSx';
import dayjs, { type Dayjs } from 'dayjs';
import { useMemo } from 'react';

import { Div, Flex } from '@salvon/components/div';
import { capitalizeFirstChar } from '@salvon/utils/string';

type MonthGridProps = {
  visibleMonth: Dayjs;
  value: Dayjs | null;
  locale: string;
  onSelectMonth: (monthIndex: number) => void;
};

export default function MonthGrid({
  visibleMonth,
  value,
  locale,
  onSelectMonth,
}: MonthGridProps) {
  const labels = useMemo(
    () =>
      Array.from({ length: 12 }, (_, i) =>
        capitalizeFirstChar(dayjs().locale(locale).month(i).format('MMM')),
      ),
    [locale],
  );

  const year = visibleMonth.year();

  return (
    <Div
      sx={{
        display: 'grid',
        gridTemplateColumns: 'repeat(3, 1fr)',
        gridAutoRows: 42,
        gap: 0.75,
      }}
    >
      {labels.map((label, i) => (
        <Flex
          key={i}
          onClick={() => onSelectMonth(i)}
          sx={cellSx({
            selected: !!value && value.year() === year && value.month() === i,
          })}
        >
          {label}
        </Flex>
      ))}
    </Div>
  );
}
