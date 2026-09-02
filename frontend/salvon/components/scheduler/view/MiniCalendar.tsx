import { Typography } from '@mui/material';

import { type RbcEvent, dayKey, eventsForDay } from '../internal';
import dayjs from 'dayjs';
import { MdChevronLeft, MdChevronRight } from 'react-icons/md';

import { Div, Flex } from '@salvon/components/div';
import { IconButton } from '@salvon/components/icon-button';
import { voc } from '@salvon/utils/object';

export type MiniCalendarProps<T> = {
  date: Date;
  onDateChange: (date: Date) => void;
  events: RbcEvent<T>[];
  locale: string;
  size?: 'sm' | 'md';
  showDots?: boolean;
  showNav?: boolean;
  labelFormat?: string;
  highlightSelected?: boolean;
  showOverflow?: boolean;
  maxDots?: number;
};

export default function MiniCalendar<T>({
  date,
  onDateChange,
  events,
  locale,
  size = 'sm',
  showDots = true,
  showNav = true,
  labelFormat = 'MMMM YYYY',
  highlightSelected = true,
  showOverflow = false,
  maxDots: maxDotsProp,
}: MiniCalendarProps<T>) {
  const focused = dayjs(date).locale(locale);
  const monthStart = focused.startOf('month');
  const gridStart = monthStart.startOf('week');
  const days = Array.from({ length: 42 }, (_, i) => gridStart.add(i, 'day'));
  const weekdays = Array.from({ length: 7 }, (_, i) =>
    gridStart.add(i, 'day').format('dd'),
  );
  const today = dayjs();
  const cell = size === 'md' ? 40 : 30;
  const maxDots = maxDotsProp ?? (size === 'md' ? 3 : 1);

  const goMonth = (dir: -1 | 1) =>
    onDateChange(focused.add(dir, 'month').toDate());

  return (
    <Div fw className="disable-salvon-animate-all">
      <Flex aCenter jBetween sx={{ mb: 1 }}>
        <Typography
          sx={{
            flex: showNav ? undefined : 1,
            textAlign: showNav ? undefined : 'center',
            fontWeight: 700,
            fontSize: size === 'md' ? 16 : 14,
            textTransform: 'capitalize',
          }}
        >
          {focused.format(labelFormat)}
        </Typography>
        {showNav && (
          <Flex aCenter sx={{ gap: 0.5 }}>
            <IconButton size="small" onClick={() => goMonth(-1)}>
              <MdChevronLeft />
            </IconButton>
            <IconButton size="small" onClick={() => goMonth(1)}>
              <MdChevronRight />
            </IconButton>
          </Flex>
        )}
      </Flex>

      <Div
        sx={{
          display: 'grid',
          gridTemplateColumns: 'repeat(7, 1fr)',
          textAlign: 'center',
        }}
      >
        {weekdays.map((w) => (
          <Typography
            key={w}
            sx={{
              fontSize: 10,
              fontWeight: 700,
              letterSpacing: 0.4,
              textTransform: 'uppercase',
              color: 'text.disabled',
              py: 0.5,
            }}
          >
            {w}
          </Typography>
        ))}

        {days.map((d) => {
          const isCurrentMonth = d.month() === focused.month();
          const isSelected = highlightSelected && d.isSame(focused, 'day');
          const isToday = d.isSame(today, 'day');
          const filled = highlightSelected ? isSelected : isToday;
          const dayEvents = showDots ? eventsForDay(events, d.toDate()) : [];
          const colors: string[] = [];
          for (const e of dayEvents) {
            if (e.color && !colors.includes(e.color)) colors.push(e.color);
          }
          const overflow =
            showOverflow && dayEvents.length > maxDots
              ? dayEvents.length - maxDots
              : 0;

          return (
            <Flex
              key={dayKey(d.toDate())}
              column
              center
              role="button"
              tabIndex={0}
              aria-label={d.format('D MMMM YYYY')}
              aria-pressed={isSelected || undefined}
              onClick={() => onDateChange(d.toDate())}
              onKeyDown={(e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                  e.preventDefault();
                  onDateChange(d.toDate());
                }
              }}
              sx={{
                height: cell,
                cursor: 'pointer',
                borderRadius: 1.5,
                position: 'relative',
                color: isCurrentMonth ? 'text.primary' : 'text.disabled',
                '&:hover': {
                  backgroundColor: filled ? undefined : 'action.hover',
                },
              }}
            >
              <Flex
                center
                sx={{
                  width: 26,
                  height: 26,
                  borderRadius: '50%',
                  fontSize: 13,
                  fontWeight: isToday || filled ? 700 : 400,
                  ...voc(
                    filled,
                    {
                      backgroundColor: 'primary.main',
                      color: 'primary.contrastText',
                    },
                    voc(isToday, { color: 'primary.main' }),
                  ),
                }}
              >
                {d.date()}
              </Flex>
              {(colors.length > 0 || overflow > 0) && (
                <Flex
                  aCenter
                  sx={{ gap: '2px', position: 'absolute', bottom: 3 }}
                >
                  {colors.slice(0, maxDots).map((c, i) => (
                    <Div
                      key={i}
                      sx={{
                        width: 4,
                        height: 4,
                        borderRadius: '50%',
                        backgroundColor: filled ? 'primary.contrastText' : c,
                      }}
                    />
                  ))}
                  {overflow > 0 && (
                    <Typography
                      component="span"
                      sx={{
                        fontSize: 8,
                        lineHeight: 1,
                        fontWeight: 600,
                        color: filled
                          ? 'primary.contrastText'
                          : 'text.disabled',
                      }}
                    >
                      +{overflow}
                    </Typography>
                  )}
                </Flex>
              )}
            </Flex>
          );
        })}
      </Div>
    </Div>
  );
}
