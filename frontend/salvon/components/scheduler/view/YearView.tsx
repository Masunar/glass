import { type RbcEvent } from '../internal';
import MiniCalendar from './MiniCalendar';
import dayjs from 'dayjs';

import { Div } from '@salvon/components/div';

export type YearViewProps<T> = {
  date: Date;
  events: RbcEvent<T>[];
  locale: string;
  onSelectDate: (date: Date) => void;
};

export default function YearView<T>({
  date,
  events,
  locale,
  onSelectDate,
}: YearViewProps<T>) {
  const yearStart = dayjs(date).startOf('year');
  const months = Array.from({ length: 12 }, (_, i) =>
    yearStart.add(i, 'month').toDate(),
  );

  return (
    <Div
      sx={{
        display: 'grid',
        gridTemplateColumns: {
          xs: '1fr',
          sm: 'repeat(2, 1fr)',
          md: 'repeat(3, 1fr)',
          lg: 'repeat(4, 1fr)',
        },
        gap: 2,
      }}
    >
      {months.map((month) => (
        <Div
          key={month.getTime()}
          sx={{
            p: 1.5,
            border: '1px solid',
            borderColor: 'divider',
            borderRadius: 2,
          }}
        >
          <MiniCalendar
            date={month}
            events={events}
            locale={locale}
            onDateChange={onSelectDate}
            showNav={false}
            labelFormat="MMMM"
            highlightSelected={false}
            showOverflow
            maxDots={3}
          />
        </Div>
      ))}
    </Div>
  );
}
