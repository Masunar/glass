import { useDroppable } from '@dnd-kit/react';

import { type KanbanColumnProps } from './types.d';

import { Flex } from '@salvon/components/div';
import { usePalette } from '@salvon/hooks/useTheme';
import { voc } from '@salvon/utils/object';
import { boolVal } from '@salvon/utils/type-transform';

export default function KanbanColumn({
  id,
  disabled,
  header,
  top,
  bottom,
  slotProps,
  bodySlotProps,
  children,
}: KanbanColumnProps) {
  const palette = usePalette();
  const { ref, isDropTarget } = useDroppable({ id, disabled });
  const active = isDropTarget && !disabled;
  const blocked = isDropTarget && disabled;

  const { sx: columnSx, ...columnProps } = slotProps ?? {};
  const { sx: bodySx, ...bodyProps } = bodySlotProps ?? {};

  return (
    <Flex
      column
      {...columnProps}
      sx={{
        gap: 0.5,
        minWidth: 300,
        flex: 1,
        ...((palette?.salvon?.kanban?.column ?? {}) as object),
        ...columnSx,
      }}
    >
      {header}
      <Flex
        ref={ref}
        column
        {...bodyProps}
        sx={{
          gap: 1,
          overflowY: 'auto',
          flex: 1,
          borderRadius: 2,
          transition: 'background-color 120ms',
          backgroundColor: 'transparent',
          outline: 'none',
          outlineColor: 'error.main',
          ...((palette?.salvon?.kanban?.columnBody ?? {}) as object),
          ...voc(
            active,
            {
              backgroundColor: 'action.hover',
              ...((palette?.salvon?.kanban?.columnBodyActive ?? {}) as object),
            },
            {},
          ),
          ...voc(
            boolVal(blocked),
            {
              outline: '1px dashed',
              ...((palette?.salvon?.kanban?.columnBodyBlocked ?? {}) as object),
            },
            {},
          ),
          ...bodySx,
        }}
      >
        {top}
        {children}
        {bottom}
      </Flex>
    </Flex>
  );
}
