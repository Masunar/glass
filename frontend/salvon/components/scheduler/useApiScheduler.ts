import { type SchedulerFetcher, type SchedulerRange } from './types.d';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

export type UseApiSchedulerOptions<T> = {
  getId: (event: T) => string;
  reloadKey?: string | number;
};

export type ApiSchedulerState<T> = {
  events: T[];
  loading: boolean;
  onRangeChange: (range: SchedulerRange) => void;
};

const rangeKey = (range: SchedulerRange) => `${+range.start}_${+range.end}`;

export const useApiScheduler = <T>(
  fetcher: SchedulerFetcher<T>,
  { getId, reloadKey }: UseApiSchedulerOptions<T>,
): ApiSchedulerState<T> => {
  const [store, setStore] = useState<Map<string, T>>(new Map());
  const [loading, setLoading] = useState(false);
  const fetchedKeys = useRef<Set<string>>(new Set());
  const pending = useRef(0);

  useEffect(() => {
    fetchedKeys.current = new Set();
    setStore(new Map());
  }, [reloadKey]);

  const events = useMemo(() => [...store.values()], [store]);

  const onRangeChange = useCallback(
    (range: SchedulerRange) => {
      const key = rangeKey(range);
      if (fetchedKeys.current.has(key)) return;
      fetchedKeys.current.add(key);
      pending.current += 1;
      setLoading(true);

      fetcher(range)
        .then((data) =>
          setStore((prev) => {
            const next = new Map(prev);
            for (const item of data) next.set(getId(item), item);
            return next;
          }),
        )
        .catch(() => {
          fetchedKeys.current.delete(key);
        })
        .finally(() => {
          pending.current -= 1;
          if (pending.current === 0) setLoading(false);
        });
    },
    [fetcher, getId],
  );

  return { events, loading, onRangeChange };
};
