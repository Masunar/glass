import SchedulerHeader from './control/SchedulerHeader';
import { type SchedulerBaseProps } from './types.d';
import { useSchedulerData } from './useSchedulerData';
import DayDetailPanel from './view/DayDetailPanel';
import MiniCalendar from './view/MiniCalendar';

import { Div } from '@salvon/components/div';
import { OverlayLoading } from '@salvon/components/progress';
import { useI18N } from '@salvon/hooks/useTranslation';

export default function CompactScheduler<T>(props: SchedulerBaseProps<T>) {
  const locale = useI18N().language;
  const { resolved, date, setDate } = useSchedulerData(props);
  const { icon, title, subtitle, loading, slotProps } = props;

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

      <Div
        sx={{
          position: 'relative',
          border: '1px solid',
          borderColor: 'divider',
          borderRadius: 2,
          overflow: 'hidden',
        }}
      >
        <OverlayLoading loading={loading}>
          <Div sx={{ p: 2 }}>
            <MiniCalendar
              date={date}
              onDateChange={setDate}
              events={resolved}
              locale={locale}
              size="md"
            />
          </Div>
        </OverlayLoading>
        <Div sx={{ p: 2, borderTop: '1px solid', borderColor: 'divider' }}>
          <DayDetailPanel
            events={resolved}
            day={date}
            locale={locale}
            onSelectEvent={props.onSelectEvent}
          />
        </Div>
      </Div>
    </Div>
  );
}
