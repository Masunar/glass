import { useEffect, useRef, useState } from 'react';

export const useTreeWidth = () => {
  const containerRef = useRef<HTMLDivElement>(null);
  const [treeWidth, setTreeWidth] = useState(0);
  const [treeHeight, setTreeHeight] = useState(0);
  const [ready, setReady] = useState(false);

  useEffect(() => {
    const el = containerRef.current;
    if (!el) return;
    const ro = new ResizeObserver((entries) => {
      requestAnimationFrame(() => {
        const { width, height } = entries[0].contentRect;
        setTreeWidth(width);
        setTreeHeight(height);
        setReady(true);
      });
    });
    ro.observe(el);
    return () => ro.disconnect();
  }, []);

  return { containerRef, treeWidth, treeHeight, ready };
};
