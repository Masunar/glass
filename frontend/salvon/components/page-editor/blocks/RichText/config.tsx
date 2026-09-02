import type { ComponentConfig } from '@puckeditor/core';

export type RichTextProps = {
  content: string | null;
};

export const richTextConfig: ComponentConfig<RichTextProps> = {
  label: 'Bogaty tekst',
  fields: {
    content: {
      type: 'richtext',
      label: 'Treść',
    } as never,
  },
  defaultProps: {
    content: null,
  },
  render: ({ content }) => <>{content}</>,
};
