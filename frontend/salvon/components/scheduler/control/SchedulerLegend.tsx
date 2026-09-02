import { Tooltip, Typography } from '@mui/material';

import { type SchedulerCalendar } from '../types.d';

import { Flex } from '@salvon/components/div';
import { type FlexProps } from '@salvon/components/div';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { type SlotItem } from '@salvon/types';

export type SchedulerLegendProps = {
  calendars: SchedulerCalendar[];
  hidden: Set<string>;
  onToggle: (id: string) => void;
  slotProps?: SlotItem<FlexProps>;
};

export default function SchedulerLegend({
  calendars,
  hidden,
  onToggle,
  slotProps,
}: SchedulerLegendProps) {
  const t = useTranslation();
  const { sx, ...rootProps } = slotProps ?? {};

  return (
    <Flex
      aCenter
      wrap
      {...rootProps}
      sx={{
        gap: 2,
        mt: 1.5,
        pt: 1.5,
        borderTop: '1px solid',
        borderColor: 'divider',
        ...sx,
      }}
    >
      {calendars.map((calendar) => {
        const isHidden = hidden.has(calendar.id);
        return (
          <Tooltip
            key={calendar.id}
            title={
              isHidden
                ? t('scheduler_calendar_show', { defaultValue: 'Pokaż' })
                : t('scheduler_calendar_hide', { defaultValue: 'Ukryj' })
            }
            arrow
          >
            <Flex
              aCenter
              onClick={() => onToggle(calendar.id)}
              role="button"
              tabIndex={0}
              onKeyDown={(e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                  e.preventDefault();
                  onToggle(calendar.id);
                }
              }}
              sx={{
                gap: 0.75,
                cursor: 'pointer',
                userSelect: 'none',
                opacity: isHidden ? 0.4 : 1,
                transition: 'opacity 0.15s ease',
                '&:hover': { opacity: isHidden ? 0.6 : 0.8 },
              }}
            >
              <Flex
                sx={{
                  width: 10,
                  height: 10,
                  borderRadius: '3px',
                  flexShrink: 0,
                  backgroundColor: calendar.color,
                }}
              />
              <Typography
                sx={{
                  fontSize: 12,
                  color: 'text.secondary',
                  textDecoration: isHidden ? 'line-through' : 'none',
                }}
              >
                {calendar.label}
              </Typography>
            </Flex>
          </Tooltip>
        );
      })}
    </Flex>
  );
}
