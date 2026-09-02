import type { OmniSearchGroupType } from '../types.d';
import Empty from './Empty';
import Group from './Group';
import type { ReactNode } from 'react';

import { Div } from '@salvon/components/div';

export type ResultsProps = {
  groups: OmniSearchGroupType[];
  total: number;
  activeIndex: number;
  noResultsItem: ReactNode;
  onSelect: (index: number) => void;
  onHover: (index: number) => void;
  rowRef: (index: number) => (el: HTMLDivElement | null) => void;
};

export default function Results({
  groups,
  total,
  activeIndex,
  noResultsItem,
  onSelect,
  onHover,
  rowRef,
}: ResultsProps) {
  let offset = 0;

  return (
    <Div sx={{ maxHeight: '58vh', overflow: 'auto', px: 1.25, py: 1 }}>
      {groups.map((group) => {
        const groupOffset = offset;
        offset += group.items.length;
        return (
          <Group
            key={group.key}
            group={group}
            offset={groupOffset}
            activeIndex={activeIndex}
            onSelect={onSelect}
            onHover={onHover}
            rowRef={rowRef}
          />
        );
      })}

      {total === 0 && <Empty>{noResultsItem}</Empty>}
    </Div>
  );
}
