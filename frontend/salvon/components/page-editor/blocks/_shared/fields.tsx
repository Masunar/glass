import { Typography } from '@mui/material';
import type { CustomField } from '@puckeditor/core';

import { Flex } from '@salvon/components/div';
import { Slider } from '@salvon/components/slider';
import { ToggleGroup } from '@salvon/components/toggle-group';

type Align = 'left' | 'center' | 'right';

export const alignField = (label = 'Wyrównanie'): CustomField<Align> => ({
  type: 'custom',
  label,
  render: ({ value, onChange }) => (
    <ToggleGroup<Align>
      fullWidth
      options={[
        { label: 'Lewo', value: 'left' },
        { label: 'Środek', value: 'center' },
        { label: 'Prawo', value: 'right' },
      ]}
      value={value ?? 'left'}
      onChange={(v) => onChange(v)}
    />
  ),
});

export const sliderField = (
  label: string,
  {
    min = 0,
    max = 100,
    unit = 'px',
  }: { min?: number; max?: number; unit?: string } = {},
): CustomField<number> => ({
  type: 'custom',
  label,
  render: ({ value, onChange }) => (
    <Flex column gap={0.5}>
      <Flex jEnd>
        <Typography sx={{ fontSize: '0.72rem', color: 'text.secondary' }}>
          {value ?? 0}
          {unit}
        </Typography>
      </Flex>
      <Slider
        value={value ?? 0}
        min={min}
        max={max}
        onChange={(v) => onChange(v as number)}
      />
    </Flex>
  ),
});
