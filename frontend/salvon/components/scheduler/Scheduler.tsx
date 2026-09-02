import CompactScheduler from './CompactScheduler';
import SchedulerShell from './SchedulerShell';
import SplitScheduler from './SplitScheduler';
import { type SchedulerBaseProps, type SchedulerProps } from './types.d';
import { type ReactElement, createElement } from 'react';

export default function Scheduler<T>({
  variant = 'calendar',
  ...props
}: SchedulerProps<T>) {
  const rest = props as SchedulerBaseProps<T>;

  switch (variant) {
    case 'split':
      return createElement(
        SplitScheduler as (p: SchedulerBaseProps<T>) => ReactElement,
        rest,
      );
    case 'compact':
      return createElement(
        CompactScheduler as (p: SchedulerBaseProps<T>) => ReactElement,
        rest,
      );
    default:
      return createElement(
        SchedulerShell as (p: SchedulerBaseProps<T>) => ReactElement,
        rest,
      );
  }
}
