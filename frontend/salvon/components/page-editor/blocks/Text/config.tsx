import type { ComponentConfig } from '@puckeditor/core';

import { alignField } from '../_shared/fields';
import Text from './Text';

export type TextProps = {
  text: string;
  align: 'left' | 'center' | 'right';
  size: 'sm' | 'md' | 'lg';
};

export const textConfig: ComponentConfig<TextProps> = {
  label: 'Tekst',
  fields: {
    text: { type: 'textarea', label: 'Treść' },
    align: alignField(),
    size: {
      type: 'radio',
      label: 'Rozmiar',
      options: [
        { label: 'Mały', value: 'sm' },
        { label: 'Średni', value: 'md' },
        { label: 'Duży', value: 'lg' },
      ],
    },
  },
  defaultProps: {
    text: 'Wpisz tekst tutaj...',
    align: 'left',
    size: 'md',
  },
  render: (props) => <Text {...props} />,
};
