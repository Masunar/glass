import type { ComponentConfig } from '@puckeditor/core';

import { alignField } from '../_shared/fields';
import Heading from './Heading';

export type HeadingProps = {
  text: string;
  level: 'h1' | 'h2' | 'h3' | 'h4' | 'h5' | 'h6';
  align: 'left' | 'center' | 'right';
  size: 'xs' | 'sm' | 'md' | 'lg' | 'xl';
  color: 'default' | 'accent';
};

export const headingConfig: ComponentConfig<HeadingProps> = {
  label: 'Nagłówek',
  fields: {
    text: { type: 'text', label: 'Tekst' },
    level: {
      type: 'select',
      label: 'Poziom',
      options: [
        { label: 'H1', value: 'h1' },
        { label: 'H2', value: 'h2' },
        { label: 'H3', value: 'h3' },
        { label: 'H4', value: 'h4' },
        { label: 'H5', value: 'h5' },
        { label: 'H6', value: 'h6' },
      ],
    },
    align: alignField(),
    size: {
      type: 'select',
      label: 'Rozmiar',
      options: [
        { label: 'XS', value: 'xs' },
        { label: 'SM', value: 'sm' },
        { label: 'MD', value: 'md' },
        { label: 'LG', value: 'lg' },
        { label: 'XL', value: 'xl' },
      ],
    },
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
    text: 'Nagłówek',
    level: 'h2',
    align: 'left',
    size: 'lg',
    color: 'default',
  },
  render: (props) => <Heading {...props} />,
};
