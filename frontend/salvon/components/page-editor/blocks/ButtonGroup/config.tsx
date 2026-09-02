import type { ComponentConfig } from '@puckeditor/core';

import { alignField, sliderField } from '../_shared/fields';
import ButtonGroup from './ButtonGroup';

type ButtonItem = {
  text: string;
  href: string;
  variant: 'outlined' | 'contained' | 'text';
};

export type ButtonGroupProps = {
  buttons: ButtonItem[];
  align: 'left' | 'center' | 'right';
  gap: number;
};

export const buttonGroupConfig: ComponentConfig<ButtonGroupProps> = {
  label: 'Grupa przycisków',
  fields: {
    buttons: {
      type: 'array',
      label: 'Przyciski',
      arrayFields: {
        text: { type: 'text', label: 'Tekst' },
        href: { type: 'text', label: 'Link' },
        variant: {
          type: 'select',
          label: 'Wariant',
          options: [
            { label: 'Outlined', value: 'outlined' },
            { label: 'Contained', value: 'contained' },
            { label: 'Text', value: 'text' },
          ],
        },
      },
    },
    align: alignField(),
    gap: sliderField('Odstęp między przyciskami (px)', { min: 0, max: 64 }),
  },
  defaultProps: {
    buttons: [
      { text: 'Przycisk 1', href: '#', variant: 'outlined' },
      { text: 'Przycisk 2', href: '#', variant: 'contained' },
    ],
    align: 'left',
    gap: 16,
  },
  render: (props) => <ButtonGroup {...props} />,
};
