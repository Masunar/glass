import { InputAdornment, Skeleton, TextField } from '@mui/material';

import CategoryRow, { ROW_HEIGHT } from './CategoryRow';
import type { CategoryNode, CategoryTreeProps } from './types';
import { useDragHover } from './useDragHover';
import { useTreeWidth } from './useTreeWidth';
import { useEffect, useRef, useState } from 'react';
import {
  type NodeApi,
  type NodeRendererProps,
  Tree,
  useSimpleTree,
} from 'react-arborist';
import { PiMagnifyingGlass } from 'react-icons/pi';

import { Card } from '@salvon/components/card';
import { Div, Flex } from '@salvon/components/div';
import { usePalette } from '@salvon/hooks/useTheme';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { voc } from '@salvon/utils/object';

export default function CategoryTree({
  data,
  autoHeight,
  autoHeightOffset,
  height,
  actions,
  onChange,
  translations,
}: CategoryTreeProps) {
  const t = useTranslation();
  const palette = usePalette();
  const [search, setSearch] = useState('');
  const { containerRef, treeWidth, treeHeight, ready } = useTreeWidth();
  const [treeData, { onMove }] = useSimpleTree<CategoryNode>(data);
  const {
    hoveredNodeId,
    handleMove,
    disableDrop,
    onDragStartCapture,
    onDragOverCapture,
    onDragEndCapture,
  } = useDragHover(onMove, treeData as CategoryNode[]);

  const searchLabel = translations?.search ?? t('search');
  const noNameLabel = translations?.noName ?? t('no_name');
  //@ts-ignore
  const dropColor = palette.primary.main;

  const containerHeight = autoHeight
    ? `calc(100dvh - ${autoHeightOffset ?? 0}px)`
    : (height ?? '100%');

  const onChangeRef = useRef(onChange);
  onChangeRef.current = onChange;

  const isMounted = useRef(false);
  useEffect(() => {
    if (!isMounted.current) {
      isMounted.current = true;
      return;
    }
    onChangeRef.current?.(treeData as CategoryNode[]);
  }, [treeData]);

  const renderNode = ({
    node,
    style,
    dragHandle,
  }: NodeRendererProps<CategoryNode>) => {
    const isDropTarget =
      ((node as NodeApi<CategoryNode> & { isDropTarget: boolean })
        .isDropTarget ||
        hoveredNodeId === node.id) &&
      !node.isDragging;

    // Plain div: react-arborist owns this row — it needs the dragHandle ref,
    // merges its own inline `style`, and matches `data-node-id` in drag capture.
    return (
      <div
        ref={dragHandle}
        data-node-id={node.id}
        className="arb-row"
        style={{
          ...style,
          outlineOffset: '-2px',
          borderRadius: 3,
          ...voc(isDropTarget, {
            outline: `2px solid ${dropColor}99`,
            backgroundColor: `${dropColor}14`,
          }),
        }}
      >
        <CategoryRow
          name={node.data.name}
          isActive={node.data.is_active ?? true}
          level={node.level}
          indent={8}
          hasChildren={(node.data.children?.length ?? 0) > 0}
          isOpen={node.isOpen}
          noName={noNameLabel}
          actions={actions?.(node.data)}
          onToggle={() => node.toggle()}
        />
      </div>
    );
  };

  return (
    <Card
      padding={0}
      className="disable-salvon-animate-all"
      sx={{
        display: 'flex',
        flexDirection: 'column',
        height: containerHeight,
      }}
    >
      <Div
        p={2}
        sx={{ borderBottom: `1px solid ${palette.divider}`, flexShrink: 0 }}
      >
        <TextField
          size="small"
          fullWidth
          placeholder={searchLabel}
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          slotProps={{
            input: {
              startAdornment: (
                <InputAdornment position="start">
                  <PiMagnifyingGlass />
                </InputAdornment>
              ),
            },
          }}
        />
      </Div>

      {/* Plain div: holds the ResizeObserver ref (useTreeWidth) and the drag
          capture handlers that react-arborist rows bubble up to. */}
      <div
        ref={containerRef}
        style={{ width: '100%', flex: 1, minHeight: 0 }}
        onDragStartCapture={onDragStartCapture}
        onDragOverCapture={onDragOverCapture}
        onDragEndCapture={onDragEndCapture}
      >
        {ready ? (
          <Tree<CategoryNode>
            data={treeData as CategoryNode[]}
            searchTerm={search}
            openByDefault
            width={treeWidth}
            height={treeHeight}
            rowHeight={ROW_HEIGHT}
            indent={24}
            childrenAccessor={(d) => d.children ?? null}
            disableDrop={disableDrop}
            onMove={handleMove}
          >
            {renderNode}
          </Tree>
        ) : (
          <Flex column gap={1} p={1.5}>
            {[0, 32, 0, 64, 32, 0, 32].map((indent, i) => (
              <Skeleton
                key={i}
                variant="rounded"
                height={ROW_HEIGHT}
                sx={{ ml: `${indent}px`, borderRadius: 1 }}
              />
            ))}
          </Flex>
        )}
      </div>
    </Card>
  );
}
