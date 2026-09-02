import {
  type DragEndEvent,
  type DragStartEvent,
  PointerSensor,
} from '@dnd-kit/react';

import { type KanbanProps } from './types.d';
import { useCallback, useEffect, useMemo, useState } from 'react';

type Args<T> = Pick<
  KanbanProps<T>,
  'items' | 'getItemId' | 'getColumnId' | 'onMove' | 'isDropAllowed'
>;

export function useKanbanDnd<T>({
  items,
  getItemId,
  getColumnId,
  onMove,
  isDropAllowed,
}: Args<T>) {
  const [activeId, setActiveId] = useState<string | null>(null);
  const [override, setOverride] = useState<Map<string, string>>(new Map());

  const sensors = useMemo(() => [PointerSensor], []);

  useEffect(() => {
    setOverride((prev) => {
      if (prev.size === 0) return prev;

      const next = new Map(prev);

      for (const item of items) {
        const id = getItemId(item);

        if (next.get(id) === getColumnId(item)) {
          next.delete(id);
        }
      }

      return next.size === prev.size ? prev : next;
    });
  }, [items, getItemId, getColumnId]);

  const getEffectiveColumnId = useCallback(
    (item: T) => override.get(getItemId(item)) ?? getColumnId(item),
    [override, getItemId, getColumnId],
  );

  const activeItem = useMemo(
    () =>
      activeId
        ? (items.find((item) => getItemId(item) === activeId) ?? null)
        : null,
    [activeId, items, getItemId],
  );

  const handleDragStart = useCallback((event: DragStartEvent) => {
    setActiveId(String(event.operation.source?.id));
  }, []);

  const handleDragEnd = useCallback(
    (event: DragEndEvent) => {
      setActiveId(null);

      if (event.canceled) return;

      const source = event.operation.source;
      const target = event.operation.target;

      if (!target) return;

      const itemId = String(source?.id);
      const toColumn = String(target.id);

      const item = items.find((item) => getItemId(item) === itemId);

      if (!item) return;

      if (getEffectiveColumnId(item) === toColumn) {
        return;
      }

      if (isDropAllowed && !isDropAllowed(item, toColumn)) {
        return;
      }

      setOverride((prev) => {
        const next = new Map(prev);
        next.set(itemId, toColumn);
        return next;
      });

      const rollback = () => {
        setOverride((prev) => {
          const next = new Map(prev);
          next.delete(itemId);
          return next;
        });
      };

      onMove(itemId, toColumn, rollback);
    },
    [items, getItemId, getEffectiveColumnId, isDropAllowed, onMove],
  );

  return {
    sensors,
    activeItem,
    getEffectiveColumnId,
    handleDragStart,
    handleDragEnd,
  };
}
