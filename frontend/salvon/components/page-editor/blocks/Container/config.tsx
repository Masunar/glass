import type { ComponentConfig } from '@puckeditor/core';

import Container, { type ContainerProps } from './Container';

export type { ContainerProps };

export const containerConfig: ComponentConfig<ContainerProps> = {
  label: 'Kontener',
  fields: {
    maxWidth: {
      type: 'select',
      label: 'Maks. szerokość',
      options: [
        { label: 'Standardowa', value: 'standard' },
        { label: 'Wąska', value: 'narrow' },
        { label: 'Szeroka', value: 'wide' },
        { label: 'Pełna', value: 'full' },
      ],
    },
    paddingX: { type: 'number', label: 'Padding po bokach (px)', min: 0 },
    paddingY: { type: 'number', label: 'Padding pionowy (px)', min: 0 },
  },
  defaultProps: {
    maxWidth: 'standard',
    paddingX: 1,
    paddingY: 32,
  },
  render: (props) => <Container {...props} />,
};
