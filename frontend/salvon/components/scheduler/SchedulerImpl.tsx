import { withDragAndDrop } from './addons';
import SchedulerHeader from './control/SchedulerHeader';
import SchedulerLegend from './control/SchedulerLegend';
import SchedulerToolbar from './control/SchedulerToolbar';
import EventChip from './entry/EventChip';
import {
  type RbcEvent,
  buildCalendarMap,
  formatLabel,
  fromRbcView,
  navigateDate,
  rangeForView,
  resolveEvents,
  toRbcView,
} from './internal';
import { schedulerSx } from './schedulerSx';
import { type SchedulerBaseProps, type SchedulerView } from './types.d';
import { useHiddenCalendars } from './useSchedulerCalendars';
import AgendaPanel from './view/AgendaPanel';
import DayDetailPanel from './view/DayDetailPanel';
import YearView from './view/YearView';
import dayjs from 'dayjs';
import isBetween from 'dayjs/plugin/isBetween';
import localeData from 'dayjs/plugin/localeData';
import localizedFormat from 'dayjs/plugin/localizedFormat';
import minMax from 'dayjs/plugin/minMax';
import weekday from 'dayjs/plugin/weekday';
import {
  type ComponentType,
  useEffect,
  useImperativeHandle,
  useMemo,
  useRef,
  useState,
} from 'react';
import { Calendar, dayjsLocalizer } from 'react-big-calendar';
import 'react-big-calendar/lib/addons/dragAndDrop/styles.css';
import 'react-big-calendar/lib/css/react-big-calendar.css';

import { Div } from '@salvon/components/div';
import { OverlayLoading } from '@salvon/components/progress';
import { usePalette } from '@salvon/hooks/useTheme';
import { useI18N } from '@salvon/hooks/useTranslation';
import { voc } from '@salvon/utils/object';

dayjs.extend(localeData);
dayjs.extend(weekday);
dayjs.extend(localizedFormat);
dayjs.extend(isBetween);
dayjs.extend(minMax);

const localizer = dayjsLocalizer(dayjs);

const DnDCalendar = withDragAndDrop(Calendar as ComponentType<any>);

const ALL_VIEWS: SchedulerView[] = ['month', 'week', 'day', 'list'];

const SCROLL_TO_TIME = new Date(1970, 0, 1, 7, 0, 0);

const tint = (color: string): string =>
  color.startsWith('#') && (color.length === 7 || color.length === 4)
    ? `${color}22`
    : color;

const hexRgb = (color: string): [number, number, number] | null => {
  let hex = color.trim();
  if (!hex.startsWith('#')) return null;
  hex = hex.slice(1);
  if (hex.length === 3)
    hex = hex
      .split('')
      .map((c) => c + c)
      .join('');
  if (hex.length !== 6) return null;
  const n = parseInt(hex, 16);
  return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
};

const readableOn = (color: string): string => {
  const rgb = hexRgb(color);
  if (!rgb) return '#fff';
  const [r, g, b] = rgb;
  const lum = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
  return lum > 0.6 ? 'rgba(0, 0, 0, 0.87)' : '#fff';
};

export default function SchedulerImpl<T>({
  events,
  getId,
  getTitle,
  getStart,
  getEnd,
  getAllDay,
  getCalendarId,
  getColor,
  calendars,
  legend,
  hiddenCalendars,
  defaultHiddenCalendars,
  onHiddenCalendarsChange,
  views = ALL_VIEWS,
  viewLabels,
  view: viewProp,
  defaultView = 'month',
  onViewChange,
  date: dateProp,
  defaultDate,
  onNavigate,
  onRangeChange,
  selectable,
  onSelectSlot,
  onSelectEvent,
  editable,
  onEventChange,
  renderEvent,
  loading,
  height = 640,
  slotProps,
  title,
  subtitle,
  icon,
  ref,
}: SchedulerBaseProps<T>) {
  const palette = usePalette();
  const locale = useI18N().language;

  if (dayjs.locale() !== locale) dayjs.locale(locale);

  const [internalView, setInternalView] = useState<SchedulerView>(defaultView);
  const requestedView = viewProp ?? internalView;
  const view = views.includes(requestedView)
    ? requestedView
    : (views[0] ?? 'month');
  const setView = (next: SchedulerView) => {
    onViewChange?.(next);
    if (viewProp === undefined) setInternalView(next);
  };

  const [internalDate, setInternalDate] = useState<Date>(
    defaultDate ?? new Date(),
  );
  const date = dateProp ?? internalDate;
  const setDate = (next: Date) => {
    onNavigate?.(next, view);
    if (dateProp === undefined) setInternalDate(next);
  };

  useImperativeHandle(ref, () => ({ navigate: setDate, setView }));

  const onRangeRef = useRef(onRangeChange);
  onRangeRef.current = onRangeChange;
  useEffect(() => {
    onRangeRef.current?.(rangeForView(date, view), view);
  }, [date, view]);

  const { hiddenSet, toggle: toggleCalendar } = useHiddenCalendars({
    hiddenCalendars,
    defaultHiddenCalendars,
    onHiddenCalendarsChange,
  });

  const calendarById = useMemo(() => buildCalendarMap(calendars), [calendars]);
  const rbcEvents = useMemo(
    () =>
      resolveEvents(
        events,
        {
          getId,
          getTitle,
          getStart,
          getEnd,
          getAllDay,
          getCalendarId,
          getColor,
        },
        calendarById,
        hiddenSet,
      ),
    [
      events,
      calendarById,
      hiddenSet,
      getId,
      getTitle,
      getStart,
      getEnd,
      getAllDay,
      getCalendarId,
      getColor,
    ],
  );

  const EventComponent = useMemo(() => {
    const Component = ({ event }: { event: RbcEvent<T> }) =>
      renderEvent ? (
        <>{renderEvent(event.source)}</>
      ) : (
        <EventChip event={event} />
      );
    return Component;
  }, [renderEvent]);

  const handleNavigate = (action: 'PREV' | 'NEXT' | 'TODAY') => {
    if (action === 'TODAY') setDate(new Date());
    else setDate(navigateDate(date, view, action === 'NEXT' ? 1 : -1));
  };

  const handleYearDayClick = (day: Date) => {
    setDate(day);
    if (views.includes('day')) setView('day');
    else if (views.includes('day_list')) setView('day_list');
    else if (views.includes('month')) setView('month');
  };

  const emitChange = (
    type: 'drop' | 'resize',
    arg: {
      event: RbcEvent<T>;
      start: Date | string;
      end: Date | string;
      isAllDay?: boolean;
    },
  ) =>
    onEventChange?.({
      event: arg.event.source,
      start: new Date(arg.start),
      end: new Date(arg.end),
      allDay: arg.isAllDay ?? arg.event.allDay,
      type,
    });

  const CalendarComponent = (editable
    ? DnDCalendar
    : Calendar) as unknown as ComponentType<Record<string, unknown>>;

  const formats = useMemo(
    () => ({
      weekdayFormat: (
        d: Date,
        culture: string,
        loc: { format: (d: Date, f: string, c: string) => string },
      ) => loc.format(d, 'dddd', culture),
    }),
    [],
  );

  const { sx: rootSx, ...rootProps } = slotProps?.root ?? {};

  return (
    <Div
      {...rootProps}
      fw
      sx={{ height, display: 'flex', flexDirection: 'column', ...rootSx }}
    >
      {(icon || title || subtitle) && (
        <SchedulerHeader
          icon={icon}
          title={title}
          subtitle={subtitle}
          slotProps={slotProps?.header}
        />
      )}

      <SchedulerToolbar
        view={view}
        views={views}
        viewLabels={viewLabels}
        label={formatLabel(date, view, locale)}
        onView={setView}
        onNavigate={handleNavigate}
        slotProps={slotProps?.toolbar}
      />

      <OverlayLoading
        loading={loading}
        sx={{ height: '100%' }}
        contentSx={{ display: 'flex', height: '100%' }}
        overlaySx={{
          borderRadius: '10px',
        }}
      >
        {view === 'list' || view === 'day_list' || view === 'year' ? (
          <Div
            fw
            className="disable-salvon-animate-all"
            sx={{
              position: 'relative',
              flex: 1,
              minHeight: 0,
              overflow: 'auto',
              p: 2,
              border: '1px solid',
              borderColor: 'divider',
              borderRadius: 2,
            }}
          >
            {view === 'list' ? (
              <AgendaPanel
                events={rbcEvents}
                from={dayjs(date).startOf('month').toDate()}
                locale={locale}
                onSelectEvent={onSelectEvent}
                showHeader={false}
              />
            ) : view === 'day_list' ? (
              <DayDetailPanel
                events={rbcEvents}
                day={date}
                locale={locale}
                onSelectEvent={onSelectEvent}
              />
            ) : (
              <YearView
                date={date}
                events={rbcEvents}
                locale={locale}
                onSelectDate={handleYearDayClick}
              />
            )}
          </Div>
        ) : (
          <Div
            fw
            className="disable-salvon-animate-all"
            sx={{
              position: 'relative',
              flex: 1,
              minHeight: 0,
              ...schedulerSx(palette),
            }}
          >
            <CalendarComponent
              culture={locale}
              localizer={localizer}
              formats={formats}
              scrollToTime={SCROLL_TO_TIME}
              events={rbcEvents}
              date={date}
              view={toRbcView(view)}
              views={views.map(toRbcView)}
              onNavigate={(next: Date) => setDate(next)}
              onView={(next: string) => setView(fromRbcView(next as never))}
              selectable={selectable}
              onSelectSlot={
                selectable
                  ? (slot: { start: Date; end: Date }) =>
                      onSelectSlot?.({
                        start: slot.start,
                        end: slot.end,
                        allDay: view === 'month',
                      })
                  : undefined
              }
              onSelectEvent={(event: RbcEvent<T>) =>
                onSelectEvent?.(event.source)
              }
              {...voc(!!editable, {
                resizable: true,
                draggableAccessor: () => true,
                onEventDrop: (arg: {
                  event: RbcEvent<T>;
                  start: Date | string;
                  end: Date | string;
                  isAllDay?: boolean;
                }) => emitChange('drop', arg),
                onEventResize: (arg: {
                  event: RbcEvent<T>;
                  start: Date | string;
                  end: Date | string;
                }) => emitChange('resize', arg),
              })}
              popup
              components={{ toolbar: () => null, event: EventComponent }}
              eventPropGetter={(event: RbcEvent<T>) => {
                if (!event.color) return {};
                const timeView = view === 'week' || view === 'day';
                return {
                  style: timeView
                    ? {
                        backgroundColor: tint(event.color),
                        color: event.color,
                        borderLeft: `3px solid ${event.color}`,
                      }
                    : {
                        backgroundColor: event.color,
                        color: readableOn(event.color),
                      },
                };
              }}
              style={{ height: '100%' }}
            />
          </Div>
        )}
      </OverlayLoading>

      {legend !== false && !!calendars?.length && (
        <SchedulerLegend
          calendars={calendars}
          hidden={hiddenSet}
          onToggle={toggleCalendar}
          slotProps={slotProps?.legend}
        />
      )}
    </Div>
  );
}
