import type { OmniSearchGroupType, OmniSearchItemType } from './types.d';
import {
  type KeyboardEvent,
  useEffect,
  useMemo,
  useRef,
  useState,
} from 'react';

type Params = {
  items: OmniSearchItemType[];
  groups?: OmniSearchGroupType[];
  limit?: number;
  onClose: () => void;
};

/** Filtering, `limit`, flattening and keyboard navigation for the results. */
export function useSearch({ items, groups, limit, onClose }: Params) {
  const [query, setQuery] = useState('');
  const [active, setActive] = useState(0);
  const rowRefs = useRef<Array<HTMLDivElement | null>>([]);

  // Flat items become a single implicit group so rendering has one path.
  const rendered = useMemo(() => {
    const source = groups?.length
      ? groups
      : [{ key: '__flat__', label: null, items }];
    const q = query.trim().toLowerCase();
    let left = limit ?? Infinity;

    return source
      .map((g) => {
        const matched = q
          ? g.items.filter((i) => i.search_name.toLowerCase().includes(q))
          : g.items;
        const sliced = matched.slice(0, Math.max(0, left));
        left -= sliced.length;
        return { ...g, items: sliced };
      })
      .filter((g) => g.items.length);
  }, [groups, items, query, limit]);

  const flat = useMemo(() => rendered.flatMap((g) => g.items), [rendered]);

  useEffect(() => setActive(0), [query, flat.length]);
  useEffect(() => {
    rowRefs.current[active]?.scrollIntoView({ block: 'nearest' });
  }, [active]);

  const select = (index: number) => {
    const item = flat[index];
    if (!item) return;
    if (!item.onSelect) return rowRefs.current[index]?.click();
    if (item.onSelect() !== false) onClose();
  };

  const rowRef = (index: number) => (el: HTMLDivElement | null) => {
    rowRefs.current[index] = el;
  };

  const onKeyDown = (e: KeyboardEvent<HTMLInputElement>) => {
    const n = flat.length;
    const to = (i: number) => (e.preventDefault(), setActive(i));

    switch (e.key) {
      case 'Escape':
        return onClose();
      case 'Enter':
        return (e.preventDefault(), select(active));
      case 'ArrowDown':
        return n && to((active + 1) % n);
      case 'ArrowUp':
        return n && to((active - 1 + n) % n);
      case 'Home':
        return n && to(0);
      case 'End':
        return n && to(n - 1);
    }
  };

  return {
    query,
    setQuery,
    active,
    setActive,
    rendered,
    flat,
    select,
    rowRef,
    onKeyDown,
  };
}
