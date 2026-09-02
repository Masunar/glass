import { useMemo, useState } from 'react';

export type UseHiddenCalendarsProps = {
  hiddenCalendars?: string[];
  defaultHiddenCalendars?: string[];
  onHiddenCalendarsChange?: (hidden: string[]) => void;
};

export type HiddenCalendarsState = {
  hiddenList: string[];
  hiddenSet: Set<string>;
  toggle: (id: string) => void;
};

export const useHiddenCalendars = ({
  hiddenCalendars,
  defaultHiddenCalendars,
  onHiddenCalendarsChange,
}: UseHiddenCalendarsProps): HiddenCalendarsState => {
  const [internal, setInternal] = useState<string[]>(
    defaultHiddenCalendars ?? [],
  );
  const hiddenList = hiddenCalendars ?? internal;
  const hiddenSet = useMemo(() => new Set(hiddenList), [hiddenList]);

  const toggle = (id: string) => {
    const next = hiddenSet.has(id)
      ? hiddenList.filter((h) => h !== id)
      : [...hiddenList, id];
    onHiddenCalendarsChange?.(next);
    if (hiddenCalendars === undefined) setInternal(next);
  };

  return { hiddenList, hiddenSet, toggle };
};
