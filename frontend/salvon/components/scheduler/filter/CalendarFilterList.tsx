import { Typography } from '@mui/material';

import { type SchedulerCalendar } from '../types.d';
import { MdCheck } from 'react-icons/md';

import { Flex } from '@salvon/components/div';
import { useTranslation } from '@salvon/hooks/useTranslation';

export type CalendarFilterListProps = {
  calendars: SchedulerCalendar[];
  hidden: Set<string>;
  onToggle: (id: string) => void;
};

export default function CalendarFilterList({
  calendars,
  hidden,
  onToggle,
}: CalendarFilterListProps) {
  const t = useTranslation();

  return (
    <Flex column>
      <Typography
        sx={{
          fontSize: 11,
          fontWeight: 700,
          letterSpacing: 0.6,
          textTransform: 'uppercase',
          color: 'text.disabled',
          mb: 1,
        }}
      >
        {t('scheduler_calendars', { defaultValue: 'Kalendarze' })}
      </Typography>

      {calendars.map((calendar) => {
        const checked = !hidden.has(calendar.id);
        return (
          <Flex
            key={calendar.id}
            aCenter
            role="checkbox"
            aria-checked={checked}
            tabIndex={0}
            onClick={() => onToggle(calendar.id)}
            onKeyDown={(e) => {
              if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                onToggle(calendar.id);
              }
            }}
            sx={{
              gap: 1,
              cursor: 'pointer',
              borderRadius: 1.5,
              px: 0.5,
              py: 0.5,
              '&:hover': { backgroundColor: 'action.hover' },
            }}
          >
            <Flex
              center
              sx={{
                width: 18,
                height: 18,
                flexShrink: 0,
                borderRadius: '4px',
                border: '2px solid',
                borderColor: calendar.color,
                backgroundColor: checked ? calendar.color : 'transparent',
                color: '#fff',
                transition: 'background-color 0.15s ease',
              }}
            >
              {checked && <MdCheck size={12} />}
            </Flex>
            <Typography
              sx={{
                fontSize: 14,
                color: checked ? 'text.primary' : 'text.secondary',
              }}
            >
              {calendar.label}
            </Typography>
          </Flex>
        );
      })}
    </Flex>
  );
}
