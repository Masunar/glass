import { DropZone } from '@puckeditor/core';

import { Div } from '@salvon/components/div';

type Props = {
  columns: number;
  gap: number;
};

export default function Grid({ columns, gap }: Props) {
  return (
    <Div
      sx={{
        display: 'grid',
        gridTemplateColumns: `repeat(${columns}, 1fr)`,
        gap: `${gap}px`,
        width: '100%',
      }}
    >
      {Array.from({ length: columns }).map((_, i) => (
        <Div key={i} sx={{ minWidth: 0 }}>
          <DropZone zone={`grid-cell-${i}`} />
        </Div>
      ))}
    </Div>
  );
}
