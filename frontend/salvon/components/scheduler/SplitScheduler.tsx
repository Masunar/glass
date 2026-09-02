import SchedulerHeader from './control/SchedulerHeader';
import CalendarFilterList from './filter/CalendarFilterList';
import { type SchedulerBaseProps } from './types.d';
import { useSchedulerData } from './useSchedulerData';
import AgendaPanel from './view/AgendaPanel';
import MiniCalendar from './view/MiniCalendar';

import { Div, Flex } from '@salvon/components/div';
import { OverlayLoading } from '@salvon/components/progress';
import { useI18N } from '@salvon/hooks/useTranslation';

export default function SplitScheduler<T>(props: SchedulerBaseProps<T>) {
  const locale = useI18N().language;
  const { resolved, hiddenSet, toggleCalendar, date, setDate } =
    useSchedulerData(props);
  const {
    icon,
    title,
    subtitle,
    calendars,
    height = 560,
    loading,
    slotProps,
  } = props;

  return (
    <Div fw {...(slotProps?.root ?? {})}>
      {(icon || title || subtitle) && (
        <SchedulerHeader
          icon={icon}
          title={title}
          subtitle={subtitle}
          slotProps={slotProps?.header}
        />
      )}

      <Flex
        sx={{
          position: 'relative',
          flexDirection: { xs: 'column', sm: 'row' },
          border: '1px solid',
          borderColor: 'divider',
          borderRadius: 2,
          overflow: 'hidden',
          height: { xs: 'auto', sm: height },
        }}
      >
        <Flex
          column
          sx={{
            width: { xs: '100%', sm: 280 },
            flexShrink: 0,
            p: 2,
            gap: 3,
            borderRight: { sm: '1px solid' },
            borderBottom: { xs: '1px solid', sm: 'none' },
            borderColor: 'divider',
            overflow: 'auto',
          }}
        >
          <MiniCalendar
            date={date}
            onDateChange={setDate}
            events={resolved}
            locale={locale}
          />
          {!!calendars?.length && (
            <CalendarFilterList
              calendars={calendars}
              hidden={hiddenSet}
              onToggle={toggleCalendar}
            />
          )}
        </Flex>

        <OverlayLoading loading={loading} sx={{ width: '100%' }}>
          <Div sx={{ flex: 1, minWidth: 0, p: 2.5, overflow: 'auto' }} fw>
            <AgendaPanel
              events={resolved}
              from={date}
              locale={locale}
              onSelectEvent={props.onSelectEvent}
            />
          </Div>
        </OverlayLoading>
      </Flex>
    </Div>
  );
}
