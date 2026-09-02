import type { ComponentConfig } from '@puckeditor/core';

import Separator from './Separator';

export type SeparatorProps = {
  thickness: number;
  color: 'default' | 'accent';
  spacing: number;
};

export const separatorConfig: ComponentConfig<SeparatorProps> = {
  label: 'Separator',
  fields: {
    thickness: { type: 'number', label: 'Grubość (px)', min: 1 },
    spacing: { type: 'number', label: 'Odstęp pionowy (px)', min: 0 },
    color: {
      type: 'radio',
      label: 'Kolor',
      options: [
        { label: 'Domyślny', value: 'default' },
        { label: 'Akcent', value: 'accent' },
      ],
    },
  },
  defaultProps: {
    thickness: 1,
    spacing: 16,
    color: 'default',
  },
  render: (props) => <Separator {...props} />,
};
