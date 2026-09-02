import { DragDropProvider, DragOverlay } from '@dnd-kit/react';
import { Typography } from '@mui/material';

import KanbanCard from './KanbanCard';
import KanbanColumn from './KanbanColumn';
import { type KanbanProps } from './types.d';
import { useKanbanDnd } from './useKanbanDnd';
import { useMemo, useState } from 'react';

import { Flex } from '@salvon/components/div';

export default function Kanban<T>({
  items,
  columns,
  getItemId,
  getColumnId,
  onMove,
  renderTaskCard,
  renderColumnHeader,
  renderColumnTop,
  renderColumnBottom,
  slotProps,
  isDropAllowed,
  canDrag,
  dragHandle,
  renderTaskDetails,
  selectedId: selectedIdProp,
  onSelectedChange,
  onCardClick,
}: KanbanProps<T>) {
  const [internalSelectedId, setInternalSelectedId] = useState<string | null>(
    null,
  );
  const isControlled = selectedIdProp !== undefined;
  const selectedId = isControlled ? selectedIdProp : internalSelectedId;

  const setSelectedId = (id: string | null) => {
    if (!isControlled) setInternalSelectedId(id);
    onSelectedChange?.(id);
  };

  const {
    sensors,
    activeItem,
    getEffectiveColumnId,
    handleDragStart,
    handleDragEnd,
  } = useKanbanDnd({ items, getItemId, getColumnId, onMove, isDropAllowed });

  const grouped = useMemo(() => {
    const map = new Map<string, T[]>(columns.map((c) => [c.id, []]));
    for (const item of items) {
      map.get(getEffectiveColumnId(item))?.push(item);
    }
    return map;
  }, [items, columns, getEffectiveColumnId]);

  const selectedItem = useMemo(
    () =>
      selectedId
        ? (items.find((i) => getItemId(i) === selectedId) ?? null)
        : null,
    [selectedId, items, getItemId],
  );

  const handleCardClick = (item: T) => {
    onCardClick?.(item);
    if (renderTaskDetails) setSelectedId(getItemId(item));
  };

  const { sx: rootSx, ...rootProps } = slotProps?.root ?? {};

  return (
    <DragDropProvider
      sensors={sensors}
      onDragStart={handleDragStart}
      onDragEnd={handleDragEnd}
    >
      <Flex
        {...rootProps}
        sx={{
          gap: 2,
          flex: 1,
          overflowX: 'auto',
          alignItems: 'stretch',
          ...rootSx,
        }}
      >
        {columns.map((column) => {
          const columnItems = grouped.get(column.id) ?? [];
          const dropDisabled =
            !!activeItem &&
            !!isDropAllowed &&
            !isDropAllowed(activeItem, column.id);

          return (
            <KanbanColumn
              key={column.id}
              id={column.id}
              disabled={dropDisabled}
              header={
                renderColumnHeader?.(column, columnItems.length) ?? (
                  <Flex
                    aCenter
                    sx={{ gap: 0.5, px: 1.5, py: 1, fontSize: 'small' }}
                  >
                    <Typography
                      component="span"
                      sx={{ opacity: 0.5, fontSize: 'inherit' }}
                    >
                      {column.label}
                    </Typography>
                    <Typography
                      component="span"
                      sx={{ opacity: 0.5, fontSize: 'inherit' }}
                    >
                      · {columnItems.length}
                    </Typography>
                  </Flex>
                )
              }
              top={renderColumnTop?.(column)}
              bottom={renderColumnBottom?.(column)}
              slotProps={slotProps?.column}
              bodySlotProps={slotProps?.columnBody}
            >
              {columnItems.map((item) => (
                <KanbanCard
                  key={getItemId(item)}
                  id={getItemId(item)}
                  disabled={canDrag ? !canDrag(item) : false}
                  dragHandle={dragHandle}
                  onClick={() => handleCardClick(item)}
                  slotProps={slotProps?.card}
                  render={(props) => renderTaskCard(item, props)}
                />
              ))}
            </KanbanColumn>
          );
        })}
      </Flex>

      <DragOverlay>
        {activeItem
          ? renderTaskCard(activeItem, {
              dragHandleRef: () => {},
              isDragging: true,
            })
          : null}
      </DragOverlay>

      {renderTaskDetails &&
        selectedItem &&
        renderTaskDetails(selectedItem, () => setSelectedId(null))}
    </DragDropProvider>
  );
}
