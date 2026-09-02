import { useIsHydrated } from '../../hooks/useIsHydrated';
import SchedulerSkeleton from './SchedulerSkeleton';
import { type SchedulerBaseProps } from './types.d';
import { type JSX, Suspense, lazy } from 'react';

const LazyImpl = lazy(() => import('./SchedulerImpl'));

export default function SchedulerShell<T>(props: SchedulerBaseProps<T>) {
  const hydrated = useIsHydrated();

  if (!hydrated) return <SchedulerSkeleton height={props.height} />;

  const Impl = LazyImpl as unknown as (p: SchedulerBaseProps<T>) => JSX.Element;

  return (
    <Suspense fallback={<SchedulerSkeleton height={props.height} />}>
      <Impl {...props} />
    </Suspense>
  );
}
