import { useEffect, useState } from 'react';

export type UseTimeoutProgressOptions = {
  onComplete?: () => void;
  interval?: number;
  paused?: boolean;
};

export type TimeoutProgress = {
  progress: number;
  remaining: number;
  elapsed: number;
  done: boolean;
};

export const useTimeoutProgress = (
  duration: number | undefined,
  { onComplete, interval = 30, paused = false }: UseTimeoutProgressOptions = {},
): TimeoutProgress => {
  const [elapsed, setElapsed] = useState(0);

  useEffect(() => {
    if (!duration || paused) return;

    const start = Date.now() - elapsed;
    const timer = setInterval(() => {
      const next = Math.min(duration, Date.now() - start);
      setElapsed(next);
      if (next >= duration) {
        clearInterval(timer);
        onComplete?.();
      }
    }, interval);

    return () => clearInterval(timer);
  }, [duration, interval, paused]);

  const remaining = duration ? Math.max(0, duration - elapsed) : 0;
  const progress = duration ? (remaining / duration) * 100 : 100;

  return { progress, remaining, elapsed, done: !!duration && remaining <= 0 };
};
