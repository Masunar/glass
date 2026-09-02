import { Divider } from '@mui/material';

import type { ToolbarProps } from '../types.d';
import {
  BG_COLORS,
  ColorControl,
  HeadingControl,
  ImageControl,
  LineHeightControl,
  LinkControl,
  TEXT_COLORS,
  ToolbarButton,
} from './control';
import { Fragment, type ReactNode } from 'react';
import {
  MdCode,
  MdDataObject,
  MdEdit,
  MdFormatAlignCenter,
  MdFormatAlignJustify,
  MdFormatAlignLeft,
  MdFormatAlignRight,
  MdFormatBold,
  MdFormatColorFill,
  MdFormatColorText,
  MdFormatItalic,
  MdFormatListBulleted,
  MdFormatListNumbered,
  MdFormatQuote,
  MdFormatStrikethrough,
  MdFormatUnderlined,
  MdRedo,
  MdSubscript,
  MdSuperscript,
  MdUndo,
} from 'react-icons/md';

import { Flex } from '@salvon/components/div';
import { useTranslation } from '@salvon/hooks/useTranslation';

type ToolbarItem =
  | { kind: 'divider' }
  | { kind: 'node'; node: ReactNode }
  | {
      kind: 'button';
      label: string;
      icon: ReactNode;
      active?: boolean;
      disabled?: boolean;
      onClick: () => void;
    };

const divider: ToolbarItem = { kind: 'divider' };

export default function Toolbar({
  editor,
  mode,
  onModeChange,
  slotProps,
}: ToolbarProps) {
  const t = useTranslation();

  if (!editor) return null;

  const isHtml = mode === 'html';
  const chain = () => editor.chain().focus();
  const textColor = editor.getAttributes('textStyle').color as
    string | undefined;
  const bgColor = editor.getAttributes('textStyle').backgroundColor as
    string | undefined;
  const lineHeight = editor.getAttributes('textStyle').lineHeight as
    string | undefined;
  const headingLevel =
    [1, 2, 3, 4, 5, 6].find((l) => editor.isActive('heading', { level: l })) ??
    0;

  const items: ToolbarItem[] = [
    {
      kind: 'button',
      label: t('tiptap.mode_editor'),
      icon: <MdEdit />,
      active: !isHtml,
      disabled: false,
      onClick: () => onModeChange('editor'),
    },
    {
      kind: 'button',
      label: t('tiptap.mode_html'),
      icon: <MdCode />,
      active: isHtml,
      disabled: false,
      onClick: () => onModeChange('html'),
    },
    divider,
    {
      kind: 'button',
      label: t('tiptap.bold'),
      icon: <MdFormatBold />,
      active: editor.isActive('bold'),
      onClick: () => chain().toggleBold().run(),
    },
    {
      kind: 'button',
      label: t('tiptap.italic'),
      icon: <MdFormatItalic />,
      active: editor.isActive('italic'),
      onClick: () => chain().toggleItalic().run(),
    },
    {
      kind: 'button',
      label: t('tiptap.underline'),
      icon: <MdFormatUnderlined />,
      active: editor.isActive('underline'),
      onClick: () => chain().toggleUnderline().run(),
    },
    {
      kind: 'button',
      label: t('tiptap.strike'),
      icon: <MdFormatStrikethrough />,
      active: editor.isActive('strike'),
      onClick: () => chain().toggleStrike().run(),
    },
    {
      kind: 'button',
      label: t('tiptap.subscript'),
      icon: <MdSubscript />,
      active: editor.isActive('subscript'),
      onClick: () => chain().toggleSubscript().run(),
    },
    {
      kind: 'button',
      label: t('tiptap.superscript'),
      icon: <MdSuperscript />,
      active: editor.isActive('superscript'),
      onClick: () => chain().toggleSuperscript().run(),
    },
    divider,
    {
      kind: 'node',
      node: (
        <ColorControl
          editor={editor}
          label={t('tiptap.text_color')}
          clearLabel={t('tiptap.color_clear')}
          disabled={isHtml}
          icon={<MdFormatColorText />}
          colors={TEXT_COLORS}
          active={!!textColor}
          current={textColor}
          onApply={(c) => chain().setColor(c).run()}
          onClear={() => chain().unsetColor().run()}
        />
      ),
    },
    {
      kind: 'node',
      node: (
        <ColorControl
          editor={editor}
          label={t('tiptap.bg_color')}
          clearLabel={t('tiptap.color_clear')}
          disabled={isHtml}
          icon={<MdFormatColorFill />}
          colors={BG_COLORS}
          active={!!bgColor}
          current={bgColor}
          onApply={(c) => chain().setBackgroundColor(c).run()}
          onClear={() => chain().unsetBackgroundColor().run()}
        />
      ),
    },
    divider,
    {
      kind: 'node',
      node: (
        <HeadingControl
          editor={editor}
          disabled={isHtml}
          label={t('tiptap.heading_select')}
          paragraphLabel={t('tiptap.paragraph')}
          headingLabel={(level) => t('tiptap.heading', { level })}
          currentLevel={headingLevel}
        />
      ),
    },
    divider,
    {
      kind: 'button',
      label: t('tiptap.bullet_list'),
      icon: <MdFormatListBulleted />,
      active: editor.isActive('bulletList'),
      onClick: () => chain().toggleBulletList().run(),
    },
    {
      kind: 'button',
      label: t('tiptap.ordered_list'),
      icon: <MdFormatListNumbered />,
      active: editor.isActive('orderedList'),
      onClick: () => chain().toggleOrderedList().run(),
    },
    {
      kind: 'node',
      node: (
        <LineHeightControl
          editor={editor}
          disabled={isHtml}
          label={t('tiptap.line_height')}
          defaultLabel={t('tiptap.line_height_default')}
          current={lineHeight}
        />
      ),
    },
    divider,
    {
      kind: 'button',
      label: t('tiptap.blockquote'),
      icon: <MdFormatQuote />,
      active: editor.isActive('blockquote'),
      onClick: () => chain().toggleBlockquote().run(),
    },
    {
      kind: 'button',
      label: t('tiptap.code_block'),
      icon: <MdDataObject />,
      active: editor.isActive('codeBlock'),
      onClick: () => chain().toggleCodeBlock().run(),
    },
    divider,
    {
      kind: 'node',
      node: (
        <LinkControl
          editor={editor}
          disabled={isHtml}
          label={t('tiptap.link')}
          removeLabel={t('tiptap.link_remove')}
          placeholder={t('tiptap.link_placeholder')}
          applyLabel={t('tiptap.link_apply')}
        />
      ),
    },
    {
      kind: 'node',
      node: (
        <ImageControl
          editor={editor}
          disabled={isHtml}
          label={t('tiptap.image')}
          urlPlaceholder={t('tiptap.image_url_placeholder')}
          insertLabel={t('tiptap.image_insert')}
          uploadLabel={t('tiptap.image_upload')}
        />
      ),
    },
    divider,
    {
      kind: 'button',
      label: t('tiptap.align_left'),
      icon: <MdFormatAlignLeft />,
      active: editor.isActive({ textAlign: 'left' }),
      onClick: () => chain().setTextAlign('left').run(),
    },
    {
      kind: 'button',
      label: t('tiptap.align_center'),
      icon: <MdFormatAlignCenter />,
      active: editor.isActive({ textAlign: 'center' }),
      onClick: () => chain().setTextAlign('center').run(),
    },
    {
      kind: 'button',
      label: t('tiptap.align_right'),
      icon: <MdFormatAlignRight />,
      active: editor.isActive({ textAlign: 'right' }),
      onClick: () => chain().setTextAlign('right').run(),
    },
    {
      kind: 'button',
      label: t('tiptap.align_justify'),
      icon: <MdFormatAlignJustify />,
      active: editor.isActive({ textAlign: 'justify' }),
      onClick: () => chain().setTextAlign('justify').run(),
    },
    divider,
    {
      kind: 'button',
      label: t('tiptap.undo'),
      icon: <MdUndo />,
      disabled: isHtml || !editor.can().undo(),
      onClick: () => chain().undo().run(),
    },
    {
      kind: 'button',
      label: t('tiptap.redo'),
      icon: <MdRedo />,
      disabled: isHtml || !editor.can().redo(),
      onClick: () => chain().redo().run(),
    },
  ];

  return (
    <Flex
      {...slotProps?.root}
      sx={{
        flexWrap: 'wrap',
        alignItems: 'center',
        gap: '2px',
        px: 0.75,
        py: 0.5,
        borderBottom: '1px solid',
        borderColor: 'divider',
        minHeight: 40,
        ...slotProps?.root?.sx,
      }}
    >
      {items.map((item, i) => {
        if (item.kind === 'divider')
          return (
            <Divider
              key={i}
              orientation="vertical"
              flexItem
              sx={{ mx: 0.5, my: 0.5 }}
            />
          );
        if (item.kind === 'node')
          return <Fragment key={i}>{item.node}</Fragment>;
        return (
          <ToolbarButton
            key={i}
            label={item.label}
            active={item.active}
            disabled={item.disabled ?? isHtml}
            onClick={item.onClick}
          >
            {item.icon}
          </ToolbarButton>
        );
      })}
    </Flex>
  );
}
