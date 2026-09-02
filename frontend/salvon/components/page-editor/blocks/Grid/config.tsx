import type { ComponentConfig } from '@puckeditor/core';

import { sliderField } from '../_shared/fields';
import Grid from './Grid';

export type GridProps = {
  columns: number;
  gap: number;
};

export const gridConfig: ComponentConfig<GridProps> = {
  label: 'Grid',
  fields: {
    columns: {
      type: 'number',
      label: 'Liczba kolumn',
      min: 1,
      max: 12,
    },
    gap: sliderField('Odstęp (px)', { min: 0, max: 96 }),
  },
  defaultProps: {
    columns: 4,
    gap: 24,
  },
  render: (props) => <Grid {...props} />,
};
