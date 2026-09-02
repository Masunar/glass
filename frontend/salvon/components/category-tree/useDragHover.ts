import type { CategoryNode } from './types';
import { type DragEvent, useCallback, useRef, useState } from 'react';
import type { MoveHandler, NodeApi } from 'react-arborist';

const collectNodeSubtreeIds = (
  node: NodeApi<CategoryNode>,
  ids: Set<string>,
): void => {
  ids.add(node.id);
  for (const child of node.children ?? []) {
    collectNodeSubtreeIds(child, ids);
  }
};

const collectAllDataIds = (node: CategoryNode, ids: Set<string>): void => {
  ids.add(node.id);
  for (const child of node.children ?? []) {
    collectAllDataIds(child, ids);
  }
};

const collectDataSubtreeIds = (
  nodes: CategoryNode[],
  targetId: string,
  ids: Set<string>,
): boolean => {
  for (const node of nodes) {
    if (node.id === targetId) {
      collectAllDataIds(node, ids);
      return true;
    }
    if (node.children && collectDataSubtreeIds(node.children, targetId, ids)) {
      return true;
    }
  }
  return false;
};

export const useDragHover = (
  onMove: MoveHandler<CategoryNode>,
  treeData: CategoryNode[],
) => {
  const hoveredNodeIdRef = useRef<string | null>(null);
  const [hoveredNodeId, setHoveredNodeId] = useState<string | null>(null);
  const draggedSubtreeIdsRef = useRef<Set<string>>(new Set());

  const handleMove = useCallback<MoveHandler<CategoryNode>>(
    (args) => {
      const { parentNode, dragNodes } = args;

      const draggedSubtreeIds = new Set<string>();
      for (const node of dragNodes) {
        collectNodeSubtreeIds(node, draggedSubtreeIds);
      }

      let effectiveParentId: string | null = args.parentId;
      if (
        hoveredNodeIdRef.current &&
        parentNode?.id !== hoveredNodeIdRef.current
      ) {
        effectiveParentId = hoveredNodeIdRef.current;
      } else if (
        !hoveredNodeIdRef.current &&
        parentNode &&
        (!parentNode.isOpen || (parentNode.data.children?.length ?? 0) === 0)
      ) {
        effectiveParentId = parentNode.parent?.id ?? null;
      }

      if (
        effectiveParentId !== null &&
        draggedSubtreeIds.has(effectiveParentId)
      ) {
        return;
      }

      if (hoveredNodeIdRef.current) {
        if (parentNode?.id !== hoveredNodeIdRef.current) {
          onMove({
            ...args,
            parentId: hoveredNodeIdRef.current,
            parentNode: null,
            index: 0,
          });
        } else {
          onMove(args);
        }
        return;
      }

      if (
        parentNode &&
        (!parentNode.isOpen || (parentNode.data.children?.length ?? 0) === 0)
      ) {
        const grandParentId = parentNode.parent?.id ?? null;
        const folderIndex = parentNode.childIndex;
        onMove({
          ...args,
          parentId: grandParentId,
          parentNode: parentNode.parent ?? null,
          index: folderIndex + 1,
        });
        return;
      }

      onMove(args);
    },
    [onMove],
  );

  const onDragStartCapture = useCallback(
    (e: DragEvent<HTMLDivElement>) => {
      const row = (e.target as Element).closest('.arb-row');
      if (!row) return;
      const nodeId = row.getAttribute('data-node-id');
      if (!nodeId) return;
      const ids = new Set<string>();
      collectDataSubtreeIds(treeData, nodeId, ids);
      draggedSubtreeIdsRef.current = ids;
    },
    [treeData],
  );

  const onDragOverCapture = useCallback((e: DragEvent<HTMLDivElement>) => {
    const row = (e.target as Element).closest('.arb-row');
    if (!row) {
      hoveredNodeIdRef.current = null;
      setHoveredNodeId(null);
      return;
    }
    const { top, height } = row.getBoundingClientRect();
    const pct = (e.clientY - top) / height;
    const rawId =
      pct > 0.25 && pct < 0.75
        ? (row.getAttribute('data-node-id') ?? null)
        : null;
    const nodeId =
      rawId && !draggedSubtreeIdsRef.current.has(rawId) ? rawId : null;
    hoveredNodeIdRef.current = nodeId;
    setHoveredNodeId(nodeId);
  }, []);

  const onDragEndCapture = useCallback(() => {
    hoveredNodeIdRef.current = null;
    setHoveredNodeId(null);
    draggedSubtreeIdsRef.current = new Set();
  }, []);

  const disableDrop = useCallback(
    ({
      parentNode,
      dragNodes,
    }: {
      parentNode: NodeApi<CategoryNode> | null;
      dragNodes: NodeApi<CategoryNode>[];
      index: number;
    }) => {
      const ids = new Set<string>();
      for (const node of dragNodes) {
        collectNodeSubtreeIds(node, ids);
      }
      const effectiveParentId =
        hoveredNodeIdRef.current ?? parentNode?.id ?? null;
      return effectiveParentId !== null && ids.has(effectiveParentId);
    },
    [],
  );

  return {
    hoveredNodeId,
    handleMove,
    disableDrop,
    onDragStartCapture,
    onDragOverCapture,
    onDragEndCapture,
  };
};
