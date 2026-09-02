import type { Config } from '@puckeditor/core';

import { type ButtonGroupProps, buttonGroupConfig } from './ButtonGroup/config';
import { type ColumnsProps, columnsConfig } from './Columns/config';
import { type ContainerProps, containerConfig } from './Container/config';
import { type GridProps, gridConfig } from './Grid/config';
import { type HeadingProps, headingConfig } from './Heading/config';
import { type RichTextProps, richTextConfig } from './RichText/config';
import { type SeparatorProps, separatorConfig } from './Separator/config';
import { type SpaceProps, spaceConfig } from './Space/config';
import { type TextProps, textConfig } from './Text/config';

export type DefaultBlocks = {
  Heading: HeadingProps;
  Text: TextProps;
  RichText: RichTextProps;
  ButtonGroup: ButtonGroupProps;
  Container: ContainerProps;
  Columns: ColumnsProps;
  Grid: GridProps;
  Separator: SeparatorProps;
  Space: SpaceProps;
};

export const defaultPuckConfig: Config<DefaultBlocks> = {
  root: { fields: {} },
  categories: {
    content: {
      title: 'Treść',
      components: ['Heading', 'Text', 'RichText', 'ButtonGroup'],
      defaultExpanded: true,
    },
    layout: {
      title: 'Układ',
      components: ['Container', 'Columns', 'Grid', 'Separator', 'Space'],
      defaultExpanded: true,
    },
  },
  components: {
    Heading: headingConfig,
    Text: textConfig,
    RichText: richTextConfig,
    ButtonGroup: buttonGroupConfig,
    Container: containerConfig,
    Columns: columnsConfig,
    Grid: gridConfig,
    Separator: separatorConfig,
    Space: spaceConfig,
  },
};
