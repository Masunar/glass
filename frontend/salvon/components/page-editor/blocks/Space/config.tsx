import type { ComponentConfig } from '@puckeditor/core';

import Space from './Space';

export type SpaceProps = {
  height: string;
};

export const spaceConfig: ComponentConfig<SpaceProps> = {
  label: 'Odstęp',
  fields: {
    height: { type: 'text', label: 'Wysokość' },
  },
  defaultProps: {
    height: '40px',
  },
  render: (props) => <Space {...props} />,
};
