import type {
  Config,
  Data,
  Overrides,
  Plugin,
  Viewports,
} from '@puckeditor/core';

import type { CSSProperties, ReactNode } from 'react';

import type { DivProps } from '@salvon/components/div';
import type { CallableNode, SlotItem } from '@salvon/types';

export type PageEditorProps = {
  config?: Config;
  value?: Data;
  onChange?: (data: Data) => void;
  onPublish?: (data: Data) => void;
  onPreview?: (data: Data) => void;
  headerTitle?: string;
  height?: CSSProperties['height'];
  iframe?: boolean;
  overrides?: Partial<Overrides>;
  plugins?: Plugin[];
  viewports?: Viewports;
  toolbar?: ReactNode;
  headerActions?: CallableNode<{
    data: Data;
    publish: () => void;
    preview: () => void;
  }>;
  blockIcons?: Record<string, ReactNode>;
  slotProps?: { root?: SlotItem<DivProps> };
};

export type PagePreviewProps = {
  config?: Config;
  value: Data;
  slotProps?: { root?: SlotItem<DivProps> };
};
