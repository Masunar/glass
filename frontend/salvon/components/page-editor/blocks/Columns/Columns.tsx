import { DropZone } from '@puckeditor/core';

import { Div } from '@salvon/components/div';

type Distribution =
  '50/50' | '33/67' | '67/33' | '33/33/33' | '25/75' | '75/25';

const distributionMap: Record<Distribution, string[]> = {
  '50/50': ['1fr', '1fr'],
  '33/67': ['1fr', '2fr'],
  '67/33': ['2fr', '1fr'],
  '33/33/33': ['1fr', '1fr', '1fr'],
  '25/75': ['1fr', '3fr'],
  '75/25': ['3fr', '1fr'],
};

type Props = {
  distribution: Distribution;
  gap: number;
  stackOnMobile: boolean;
};

export default function Columns({ distribution, gap, stackOnMobile }: Props) {
  const columns = distributionMap[distribution];

  return (
    <Div
      sx={{
        display: 'grid',
        gridTemplateColumns: {
          xs: stackOnMobile ? '1fr' : columns.join(' '),
          md: columns.join(' '),
        },
        gap: `${gap}px`,
        width: '100%',
      }}
    >
      {columns.map((_, i) => (
        <Div key={i} sx={{ minWidth: 0 }}>
          <DropZone zone={`column-${i}`} />
        </Div>
      ))}
    </Div>
  );
}
