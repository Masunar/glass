import RangeMonthPanel from './components/RangeMonthPanel';
import TimeStepInput from './components/TimeStepInput';
import type {
  CalendarView,
  DateRange,
  PickerCalendarView,
  RangePickerCalendarProps,
  RangePreset,
  TimeView,
} from './types.d';
import dayjs, { type Dayjs } from 'dayjs';
import { useMemo, useState } from 'react';

import { Button } from '@salvon/components/button';
import { Div, Flex } from '@salvon/components/div';
import { useCurrentLocale } from '@salvon/hooks/useLocale';
import { usePalette } from '@salvon/hooks/useTheme';
import { voc } from '@salvon/utils/object';

const CAL_ORDER: CalendarView[] = ['year', 'month', 'day'];
const TIME_ORDER: TimeView[] = ['hours', 'minutes', 'seconds'];

const endpointLabelSx = {
  fontSize: '0.7rem',
  fontWeight: 700,
  letterSpacing: '0.06em',
  color: 'text.secondary',
};

type Endpoint = keyof DateRange;

export default function RangePickerCalendar({
  fw = false,
  value,
  defaultValue = null,
  onChange,
  onApply,
  onClear,
  presets,
  singleMonth = true,
  minDate,
  maxDate,
  locale: localeProp,
  format = 'D MMM YYYY',
  views,
  hourCycle = 24,
  hideFooter = false,
  hideOutsideDays = true,
  renderFooter,
  labels,
  slotProps,
}: RangePickerCalendarProps) {
  const is12h = hourCycle === 12;
  const palette = usePalette();
  const appLocale = useCurrentLocale();
  const locale = localeProp ?? appLocale;

  const enabledViews = useMemo(
    () => new Set<PickerCalendarView>(views ?? CAL_ORDER),
    [views],
  );
  const activeViews = CAL_ORDER.filter((v) => enabledViews.has(v));
  const timeViews = TIME_ORDER.filter((v) => enabledViews.has(v));
  const showCalendar = activeViews.length > 0;
  const showTime = timeViews.length > 0;
  const commitUnit = (activeViews[activeViews.length - 1] ?? 'day') as
    'year' | 'month' | 'day';

  const timeFormat = useMemo(() => {
    const parts: string[] = [];
    if (timeViews.includes('hours')) {
      parts.push(is12h ? 'hh' : 'HH');
    }
    if (timeViews.includes('minutes')) {
      parts.push('mm');
    }
    if (timeViews.includes('seconds')) {
      parts.push('ss');
    }
    let fmt = parts.join(':');
    if (fmt && is12h && timeViews.includes('hours')) {
      fmt += ' A';
    }
    return fmt;
  }, [timeViews, is12h]);
  const fullFormat = !showCalendar
    ? timeFormat || format
    : timeFormat
      ? `${format} ${timeFormat}`
      : format;

  const [internal, setInternal] = useState<DateRange>(
    defaultValue ?? { start: null, end: null },
  );
  const range = value ?? internal;

  const [leftMonth, setLeftMonth] = useState<Dayjs>(() =>
    (range.start ?? dayjs()).startOf('month'),
  );
  const [hovered, setHovered] = useState<Dayjs | null>(null);

  function update(next: DateRange) {
    onChange?.(next, {
      start: next.start ? next.start.locale(locale).format(fullFormat) : '',
      end: next.end ? next.end.locale(locale).format(fullFormat) : '',
    });
    if (value === undefined) {
      setInternal(next);
    }
  }

  /** Apply a picked date to `day` while keeping the endpoint's time-of-day. */
  function withTime(day: Dayjs, prev: Dayjs | null): Dayjs {
    if (!prev) {
      return day;
    }
    return day.hour(prev.hour()).minute(prev.minute()).second(prev.second());
  }

  function selectDay(picked: Dayjs) {
    const { start, end } = range;
    if (!start || (start && end)) {
      update({ start: withTime(picked, start), end: null });
      return;
    }
    if (picked.isBefore(start, commitUnit)) {
      update({ start: withTime(picked, start), end: withTime(start, end) });
    } else {
      update({ start, end: withTime(picked, end) });
    }
  }

  function changeTime(endpoint: Endpoint, next: Dayjs) {
    // With a calendar, time only edits an already-picked endpoint. Time-only
    // (no calendar) has nothing to seed the date, so accept the edit directly.
    if (!range[endpoint] && showCalendar) {
      return;
    }
    update({ ...range, [endpoint]: next });
  }

  function applyPreset(next: DateRange) {
    update(next);
    if (next.start) {
      setLeftMonth(next.start.startOf('month'));
    }
  }

  const dayCount =
    range.start && range.end ? range.end.diff(range.start, 'day') + 1 : null;

  const summary =
    range.start || range.end
      ? [
          range.start ? range.start.locale(locale).format(fullFormat) : '—',
          range.end ? range.end.locale(locale).format(fullFormat) : '—',
        ].join(showTime ? ' → ' : ' - ') +
        (!showTime && dayCount ? `, ${dayCount} ${labels?.days ?? 'dni'}` : '')
      : '';

  const paper = {
    p: 1.5,
    borderRadius: '14px',
    border: '1px solid',
    borderColor: 'divider',
    backgroundColor: 'background.paper',
    ...(fw
      ? { display: 'block', width: '100%' }
      : { display: 'inline-block', maxWidth: '100%' }),
    ...((palette?.salvon?.popover?.paper ?? { boxShadow: 3 }) as any),
  };

  return (
    <Div
      {...slotProps?.root}
      sx={{
        ...paper,
        ...voc(singleMonth, { maxWidth: '330px' }, { maxWidth: '660px' }),
        ...(slotProps?.root?.sx ?? {}),
      }}
    >
      {showCalendar && (
        <Flex
          gap={2}
          onMouseLeave={() => setHovered(null)}
          justify="center"
          wrap
        >
          <Div sx={{ minWidth: 290 }}>
            <RangeMonthPanel
              visibleMonth={leftMonth}
              range={range}
              hovered={hovered}
              locale={locale}
              arrows={singleMonth ? 'both' : 'left'}
              activeViews={activeViews}
              minDate={minDate}
              maxDate={maxDate}
              hideOutsideDays={hideOutsideDays}
              onVisibleMonthChange={setLeftMonth}
              onSelectDay={selectDay}
              onHoverDay={setHovered}
            />
          </Div>

          {!singleMonth && (
            <>
              <Div
                sx={{
                  width: '1px',
                  backgroundColor: 'divider',
                  alignSelf: 'stretch',
                  display: { xs: 'none', lg: 'block' },
                }}
              />

              <Div sx={{ minWidth: 290 }}>
                <RangeMonthPanel
                  visibleMonth={leftMonth.add(1, 'month')}
                  range={range}
                  hovered={hovered}
                  locale={locale}
                  arrows="right"
                  activeViews={activeViews}
                  minDate={minDate}
                  maxDate={maxDate}
                  hideOutsideDays={hideOutsideDays}
                  onVisibleMonthChange={(m) =>
                    setLeftMonth(m.subtract(1, 'month'))
                  }
                  onSelectDay={selectDay}
                  onHoverDay={setHovered}
                />
              </Div>
            </>
          )}
        </Flex>
      )}

      {showTime && (
        <Flex
          gap={2}
          wrap
          sx={{
            ...(showCalendar && {
              pt: 1.5,
              mt: 1.5,
              borderTop: '1px solid',
              borderColor: 'divider',
            }),
          }}
        >
          <Flex aCenter gap={1} sx={{ flex: 1, minWidth: 200 }}>
            <Div sx={endpointLabelSx}>{labels?.from ?? 'OD'}</Div>
            <TimeStepInput
              value={range.start}
              timeViews={timeViews}
              is12h={is12h}
              disabled={showCalendar && !range.start}
              onChange={(v) => changeTime('start', v)}
            />
          </Flex>
          <Flex aCenter gap={1} sx={{ flex: 1, minWidth: 200 }}>
            <Div sx={endpointLabelSx}>{labels?.to ?? 'DO'}</Div>
            <TimeStepInput
              value={range.end}
              timeViews={timeViews}
              is12h={is12h}
              disabled={showCalendar && !range.end}
              onChange={(v) => changeTime('end', v)}
            />
          </Flex>
        </Flex>
      )}

      {presets && presets.length > 0 && (
        <Flex
          gap={1}
          wrap
          sx={{
            pt: 1.5,
            mt: 1.5,
            borderTop: '1px solid',
            borderColor: 'divider',
          }}
        >
          {presets.map((p: RangePreset, i: number) => (
            <Button
              key={i}
              size="small"
              variant="outlined"
              color="inherit"
              onClick={() => applyPreset(p.value())}
              sx={{
                borderRadius: '999px',
                borderColor: 'divider',
                color: 'text.primary',
              }}
            >
              {p.label}
            </Button>
          ))}
        </Flex>
      )}

      {!hideFooter &&
        (renderFooter ? (
          range.start || range.end ? (
            renderFooter({
              value: range,
              formattedValue: {
                start: range.start
                  ? range.start.locale(locale).format(fullFormat)
                  : '',
                end: range.end
                  ? range.end.locale(locale).format(fullFormat)
                  : '',
              },
              dayCount,
              setValue: update,
              apply: (next) => {
                const applied = next ?? range;
                if (next) {
                  update(next);
                }
                onApply?.(applied);
              },
              clear: () => {
                update({ start: null, end: null });
                onClear?.();
              },
            })
          ) : null
        ) : (
          <Flex
            jBetween
            aCenter
            gap={1}
            wrap
            sx={{
              pt: 1.5,
              mt: 1.5,
              borderTop: '1px solid',
              borderColor: 'divider',
            }}
          >
            <Div
              sx={{
                fontSize: '0.85rem',
                color: 'text.secondary',
                fontWeight: 500,
              }}
            >
              {summary}
            </Div>
            <Flex gap={1} sx={{ ml: 'auto' }}>
              <Button
                size="small"
                variant="outlined"
                color="inherit"
                onClick={() => {
                  update({ start: null, end: null });
                  onClear?.();
                }}
                sx={{ borderColor: 'divider', color: 'text.primary' }}
              >
                {labels?.clear ?? 'Wyczyść'}
              </Button>
              <Button size="small" onClick={() => onApply?.(range)}>
                {labels?.apply ?? 'Zastosuj'}
              </Button>
            </Flex>
          </Flex>
        ))}
    </Div>
  );
}
