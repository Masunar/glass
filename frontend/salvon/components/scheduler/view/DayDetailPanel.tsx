import { Typography } from '@mui/material';

import AgendaEventRow from '../entry/AgendaEventRow';
import { type RbcEvent, eventsForDay } from '../internal';
import dayjs from 'dayjs';

import { Div, Flex } from '@salvon/components/div';
import { useTranslation } from '@salvon/hooks/useTranslation';

export type DayDetailPanelProps<T> = {
  events: RbcEvent<T>[];
  day: Date;
  locale: string;
  onSelectEvent?: (event: T) => void;
};

export default function DayDetailPanel<T>({
  events,
  day,
  locale,
  onSelectEvent,
}: DayDetailPanelProps<T>) {
  const t = useTranslation();
  const dayEvents = eventsForDay(events, day);

  return (
    <Flex column fw>
      <Typography
        sx={{
          fontSize: 12,
          fontWeight: 700,
          letterSpacing: 0.5,
          textTransform: 'uppercase',
          color: 'text.secondary',
          mb: 1.5,
        }}
      >
        {dayjs(day).locale(locale).format('dddd, D MMMM')}
      </Typography>

      {dayEvents.length === 0 ? (
        <Flex sx={{ color: 'text.disabled', fontSize: 14, py: 2 }}>
          {t('no_results', { defaultValue: 'Brak zdarzeń' })}
        </Flex>
      ) : (
        <Div
          sx={{
            '& > *:not(:last-child)': {
              borderBottom: '1px solid',
              borderColor: 'divider',
            },
          }}
        >
          {dayEvents.map((event) => (
            <AgendaEventRow
              key={event.id}
              event={event}
              locale={locale}
              onClick={
                onSelectEvent ? () => onSelectEvent(event.source) : undefined
              }
            />
          ))}
        </Div>
      )}
    </Flex>
  );
}
