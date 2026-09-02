import { type NodeViewProps, NodeViewWrapper } from '@tiptap/react';

import { useRef } from 'react';

export default function ResizableImageView({
  node,
  updateAttributes,
  selected,
  editor,
}: NodeViewProps) {
  const imgRef = useRef<HTMLImageElement>(null);
  const width = node.attrs.width as string | null;

  const startResize = (event: React.PointerEvent) => {
    event.preventDefault();
    const img = imgRef.current;
    if (!img) return;

    const startX = event.clientX;
    const startWidth = img.getBoundingClientRect().width;
    const pmDom = editor.view.dom as HTMLElement;
    const cs = getComputedStyle(pmDom);
    const maxWidth =
      pmDom.clientWidth -
      parseFloat(cs.paddingLeft || '0') -
      parseFloat(cs.paddingRight || '0');

    const onMove = (e: PointerEvent) => {
      const next = Math.round(
        Math.max(40, Math.min(startWidth + (e.clientX - startX), maxWidth)),
      );
      updateAttributes({ width: `${next}px` });
    };
    const onUp = () => {
      document.removeEventListener('pointermove', onMove);
      document.removeEventListener('pointerup', onUp);
    };

    document.addEventListener('pointermove', onMove);
    document.addEventListener('pointerup', onUp);
  };

  return (
    <NodeViewWrapper
      className={`rte-img-wrap${selected ? ' is-selected' : ''}`}
      data-drag-handle
    >
      <img
        ref={imgRef}
        src={node.attrs.src}
        alt={node.attrs.alt ?? ''}
        title={node.attrs.title ?? ''}
        style={{ width: width ?? 'auto' }}
        draggable={false}
      />
      {selected && editor.isEditable && (
        <span
          className="rte-img-handle"
          onPointerDown={startResize}
          onMouseDown={(e) => e.preventDefault()}
        />
      )}
    </NodeViewWrapper>
  );
}
