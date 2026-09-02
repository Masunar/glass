import { type ReactNode } from 'react';

import { type DivProps, type FlexProps } from '@salvon/components/div';
import { type SlotItem } from '@salvon/types';

export type KanbanColumn = {
  id: string;
  label: string;
  color?: string;
  limit?: number;
};

// @dnd-kit/react activates drag through a ref on the handle element, not through spread props.
export type DragHandleRef = (element: Element | null) => void;

export type KanbanCardRenderProps = {
  dragHandleRef: DragHandleRef;
  isDragging: boolean;
};

export type KanbanSlotProps = {
  root?: SlotItem<FlexProps>;
  column?: SlotItem<FlexProps>;
  columnBody?: SlotItem<FlexProps>;
  card?: SlotItem<DivProps>;
};

export type KanbanProps<T> = {
  items: T[];
  columns: KanbanColumn[];
  getItemId: (item: T) => string;
  getColumnId: (item: T) => string;
  onMove: (itemId: string, toColumn: string, rollback: () => void) => void;
  renderTaskCard: (item: T, props: KanbanCardRenderProps) => ReactNode;
  renderColumnHeader?: (column: KanbanColumn, count: number) => ReactNode;
  renderColumnTop?: (column: KanbanColumn) => ReactNode;
  renderColumnBottom?: (column: KanbanColumn) => ReactNode;
  slotProps?: KanbanSlotProps;
  isDropAllowed?: (item: T, toColumn: string) => boolean;
  canDrag?: (item: T) => boolean;
  dragHandle?: boolean;

  renderTaskDetails?: (item: T, close: () => void) => ReactNode;
  selectedId?: string | null;
  onSelectedChange?: (id: string | null) => void;
  onCardClick?: (item: T) => void;
};

export type KanbanColumnProps = {
  id: string;
  disabled?: boolean;
  header: ReactNode;
  top?: ReactNode;
  bottom?: ReactNode;
  slotProps?: SlotItem<FlexProps>;
  bodySlotProps?: SlotItem<FlexProps>;
  children: ReactNode;
};

export type KanbanCardProps = {
  id: string;
  disabled?: boolean;
  dragHandle?: boolean;
  onClick?: () => void;
  slotProps?: SlotItem<DivProps>;
  render: (props: KanbanCardRenderProps) => ReactNode;
};
