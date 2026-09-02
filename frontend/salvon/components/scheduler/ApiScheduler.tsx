import Scheduler from './Scheduler';
import { type ApiSchedulerProps } from './types.d';
import { useApiScheduler } from './useApiScheduler';

export default function ApiScheduler<T>({
  fetcher,
  reloadKey,
  onRangeChange: userOnRangeChange,
  ...rest
}: ApiSchedulerProps<T>) {
  const { events, loading, onRangeChange } = useApiScheduler<T>(fetcher, {
    getId: rest.getId,
    reloadKey,
  });

  return (
    <Scheduler<T>
      {...rest}
      events={events}
      loading={loading}
      onRangeChange={(range, view) => {
        onRangeChange(range);
        userOnRangeChange?.(range, view);
      }}
    />
  );
}
