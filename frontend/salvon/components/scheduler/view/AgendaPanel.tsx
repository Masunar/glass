import { Typography } from '@mui/material';

import AgendaEventRow from '../entry/AgendaEventRow';
import { type RbcEvent, dayKey, groupByDay } from '../internal';
import dayjs from 'dayjs';

import { Div, Flex } from '@salvon/components/div';
import { useTranslation } from '@salvon/hooks/useTranslation';

export type AgendaPanelProps<T> = {
  events: RbcEvent<T>[];
  from: Date;
  locale: string;
  onSelectEvent?: (event: T) => void;
  showHeader?: boolean;
};

export default function AgendaPanel<T>({
  events,
  from,
  locale,
  onSelectEvent,
  showHeader = true,
}: AgendaPanelProps<T>) {
  const t = useTranslation();
  const groups = groupByDay(events, from);

  return (
    <Flex column fh sx={{ minWidth: 0 }}>
      {showHeader && (
        <Typography sx={{ fontWeight: 700, fontSize: 18, mb: 2 }}>
          {t('scheduler_view_list', { defaultValue: 'Agenda' })}
        </Typography>
      )}

      {groups.length === 0 && (
        <Flex center fh sx={{ color: 'text.disabled', fontSize: 14, py: 4 }}>
          {t('no_results', { defaultValue: 'Brak zdarzeń' })}
        </Flex>
      )}

      <Flex column sx={{ gap: 3, overflow: 'auto' }}>
        {groups.map((group) => {
          const d = dayjs(group.day).locale(locale);
          return (
            <Flex
              key={dayKey(group.day)}
              sx={{ gap: 2, alignItems: 'flex-start' }}
            >
              <Flex column aCenter sx={{ width: 44, flexShrink: 0, pt: 0.5 }}>
                <Typography
                  sx={{
                    fontSize: 11,
                    fontWeight: 700,
                    textTransform: 'uppercase',
                    color: 'text.secondary',
                  }}
                >
                  {d.format('ddd')}
                </Typography>
                <Typography
                  sx={{ fontSize: 22, fontWeight: 700, lineHeight: 1.1 }}
                >
                  {d.format('D')}
                </Typography>
              </Flex>
              <Div
                sx={{
                  flex: 1,
                  minWidth: 0,
                  borderRadius: 2,
                  border: '1px solid',
                  borderColor: 'divider',
                  overflow: 'hidden',
                  '& > *:not(:last-child)': {
                    borderBottom: '1px solid',
                    borderColor: 'divider',
                  },
                }}
              >
                {group.events.map((event) => (
                  <AgendaEventRow
                    key={event.id}
                    event={event}
                    locale={locale}
                    onClick={
                      onSelectEvent
                        ? () => onSelectEvent(event.source)
                        : undefined
                    }
                  />
                ))}
              </Div>
            </Flex>
          );
        })}
      </Flex>
    </Flex>
  );
}
