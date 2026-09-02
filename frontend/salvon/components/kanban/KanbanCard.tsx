import { useDraggable } from '@dnd-kit/react';

import { type KanbanCardProps } from './types.d';

import { Div } from '@salvon/components/div';
import { usePalette } from '@salvon/hooks/useTheme';

export default function KanbanCard({
  id,
  disabled,
  dragHandle,
  onClick,
  slotProps,
  render,
}: KanbanCardProps) {
  const palette = usePalette();
  const { ref, handleRef, isDragging } = useDraggable({ id, disabled });

  const { sx, ...cardProps } = slotProps ?? {};

  return (
    <Div
      ref={ref}
      onClick={onClick}
      {...cardProps}
      sx={{
        touchAction: 'none',
        opacity: isDragging ? 0.4 : 1,
        cursor: disabled || dragHandle ? 'default' : 'grab',
        ...((palette?.salvon?.kanban?.card ?? {}) as object),
        ...sx,
      }}
    >
      {render({ dragHandleRef: handleRef, isDragging })}
    </Div>
  );
}
