import type { OmniSearchGroupType } from '../types.d';
import Row from './Row';
import type { ReactNode } from 'react';

import { Div } from '@salvon/components/div';
import { useIsDarkMode, usePalette } from '@salvon/hooks/useTheme';

export type GroupProps = {
  group: OmniSearchGroupType;
  activeIndex: number;
  /** Global index of this group's first row across all groups. */
  offset: number;
  onSelect: (index: number) => void;
  onHover: (index: number) => void;
  rowRef: (index: number) => (el: HTMLDivElement | null) => void;
};

export default function Group({
  group,
  activeIndex,
  offset,
  onSelect,
  onHover,
  rowRef,
}: GroupProps) {
  return (
    <Div sx={{ mb: 0.5 }}>
      {group.label != null && <Label>{group.label}</Label>}
      {group.items.map((item, i) => {
        const index = offset + i;
        return (
          <Row
            key={item.key}
            ref={rowRef(index)}
            item={item}
            active={index === activeIndex}
            onClick={() => onSelect(index)}
            onMouseMove={() => onHover(index)}
          />
        );
      })}
    </Div>
  );
}

function Label({ children }: { children: ReactNode }) {
  const palette = usePalette();
  const dark = useIsDarkMode();
  const cfg = palette.salvon?.omni_search ?? {};

  return (
    <Div
      sx={{
        px: 1.5,
        pt: 1.5,
        pb: 0.75,
        fontSize: '0.68rem',
        fontWeight: 700,
        letterSpacing: '0.08em',
        textTransform: 'uppercase',
        color: cfg.header ?? (dark ? '#6b7280' : '#94a3b8'),
      }}
    >
      {children}
    </Div>
  );
}
