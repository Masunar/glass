import type { ComponentConfig } from '@puckeditor/core';

import { sliderField } from '../_shared/fields';
import Columns from './Columns';

export type ColumnsProps = {
  distribution: '50/50' | '33/67' | '67/33' | '33/33/33' | '25/75' | '75/25';
  gap: number;
  stackOnMobile: boolean;
};

export const columnsConfig: ComponentConfig<ColumnsProps> = {
  label: 'Kolumny',
  fields: {
    distribution: {
      type: 'select',
      label: 'Podział',
      options: [
        { label: '50% / 50%', value: '50/50' },
        { label: '33% / 67%', value: '33/67' },
        { label: '67% / 33%', value: '67/33' },
        { label: '33% / 33% / 33%', value: '33/33/33' },
        { label: '25% / 75%', value: '25/75' },
        { label: '75% / 25%', value: '75/25' },
      ],
    },
    gap: sliderField('Odstęp (px)', { min: 0, max: 96 }),
    stackOnMobile: {
      type: 'radio',
      label: 'Stos na mobile',
      options: [
        { label: 'Tak', value: true },
        { label: 'Nie', value: false },
      ],
    },
  },
  defaultProps: {
    distribution: '50/50',
    gap: 32,
    stackOnMobile: true,
  },
  render: (props) => <Columns {...props} />,
};
