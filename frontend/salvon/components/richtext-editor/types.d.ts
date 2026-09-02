import type { Editor } from '@tiptap/react';

import type { TextareaHTMLAttributes } from 'react';

import type { DivProps, FlexProps } from '@salvon/components/div';
import type { SlotItem } from '@salvon/types';

export type RichTextEditorMode = 'editor' | 'html';

export type RichTextEditorProps = {
  value?: string;
  onChange?: (value: string) => void;
  disabled?: boolean;
  slotProps?: {
    root?: SlotItem<DivProps>;
    toolbar?: SlotItem<FlexProps>;
    textarea?: SlotItem<TextareaHTMLAttributes<HTMLTextAreaElement>>;
  };
};

export type ToolbarProps = {
  editor: Editor | null;
  mode: RichTextEditorMode;
  onModeChange: (mode: RichTextEditorMode) => void;
  slotProps?: { root?: SlotItem<FlexProps> };
};
