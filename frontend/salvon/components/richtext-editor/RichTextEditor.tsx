import { Extension } from '@tiptap/core';
import Subscript from '@tiptap/extension-subscript';
import Superscript from '@tiptap/extension-superscript';
import TextAlign from '@tiptap/extension-text-align';
import {
  BackgroundColor,
  Color,
  LineHeight,
  TextStyle,
} from '@tiptap/extension-text-style';
import { EditorContent, useEditor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';

import Toolbar from './components/Toolbar';
import ResizableImage from './extensions/ResizableImage';
import type { RichTextEditorMode, RichTextEditorProps } from './types.d';
import { useEffect, useRef, useState } from 'react';

import { Div } from '@salvon/components/div';

const ListKeyboardFix = Extension.create({
  name: 'listKeyboardFix',
  priority: 1000,
  addKeyboardShortcuts() {
    return {
      Tab: () => {
        if (!this.editor.isActive('listItem')) return false;
        this.editor.commands.sinkListItem('listItem');
        return true;
      },
      'Shift-Tab': () => {
        if (!this.editor.isActive('listItem')) return false;
        this.editor.commands.liftListItem('listItem');
        return true;
      },
    };
  },
});

export default function RichTextEditor({
  value,
  onChange,
  disabled = false,
  slotProps,
}: RichTextEditorProps) {
  const debounceRef = useRef<ReturnType<typeof setTimeout>>(undefined);
  const lastEmittedRef = useRef<string | null>(null);
  const [mode, setMode] = useState<RichTextEditorMode>('editor');

  const editor = useEditor({
    extensions: [
      StarterKit.configure({
        link: { openOnClick: false, autolink: true },
      }),
      TextAlign.configure({ types: ['heading', 'paragraph'] }),
      TextStyle,
      Color,
      BackgroundColor,
      LineHeight,
      Subscript,
      Superscript,
      ResizableImage.configure({ allowBase64: true }),
      ListKeyboardFix,
    ],
    editable: !disabled,
    onUpdate: ({ editor: updatedEditor }) => {
      if (!onChange) return;
      const html = updatedEditor.getHTML();
      lastEmittedRef.current = html;
      clearTimeout(debounceRef.current);
      debounceRef.current = setTimeout(() => onChange(html), 300);
    },
  });

  useEffect(() => () => clearTimeout(debounceRef.current), []);

  useEffect(() => {
    if (!editor) return;
    const incoming = value ?? '';
    if (incoming === lastEmittedRef.current) return;
    if (incoming === editor.getHTML()) return;
    editor.commands.setContent(incoming, { emitUpdate: false });
  }, [editor, value]);

  useEffect(() => {
    if (!editor) return;
    editor.setEditable(!disabled);
  }, [editor, disabled]);

  useEffect(() => {
    if (!editor) return;
    if (mode === 'editor') {
      editor.commands.setContent(value ?? '', { emitUpdate: false });
    }
  }, [mode, editor]);

  return (
    <Div
      {...slotProps?.root}
      sx={{
        border: '1px solid',
        borderColor: 'divider',
        borderRadius: '4px',
        overflow: 'hidden',
        transition: 'border-color 0.15s, box-shadow 0.15s',
        '&:hover': {
          borderColor: 'text.primary',
        },
        '&:focus-within': {
          borderColor: 'primary.main',
          boxShadow: (theme) => `0 0 0 2px ${theme.palette.primary.main}26`,
        },
        '& .ProseMirror': {
          minHeight: 120,
          padding: '10px 14px',
          outline: 'none',
          fontSize: '0.875rem',
          lineHeight: 1.6,
          '& > * + *': { marginTop: '0.5em' },
          '& p': { margin: 0 },
          '& ul, & ol': { paddingLeft: '1.4em', margin: 0 },
          '& h1': { fontSize: '1.4em', fontWeight: 700, lineHeight: 1.3 },
          '& h2': { fontSize: '1.2em', fontWeight: 700, lineHeight: 1.3 },
          '& h3': { fontSize: '1.05em', fontWeight: 600, lineHeight: 1.3 },
          '& strong': { fontWeight: 700 },
          '& a': {
            color: 'primary.main',
            textDecoration: 'underline',
            cursor: 'pointer',
          },
          '& img': {
            maxWidth: '100%',
            height: 'auto',
            borderRadius: '4px',
            display: 'block',
          },
          '& .rte-img-wrap': {
            position: 'relative',
            display: 'inline-block',
            maxWidth: '100%',
            lineHeight: 0,
          },
          '& .rte-img-wrap.is-selected img': {
            outline: (theme) => `2px solid ${theme.palette.primary.main}`,
          },
          '& .rte-img-handle': {
            position: 'absolute',
            right: '-5px',
            bottom: '-5px',
            width: 12,
            height: 12,
            borderRadius: '50%',
            backgroundColor: 'primary.main',
            border: '2px solid',
            borderColor: 'background.paper',
            cursor: 'nwse-resize',
            touchAction: 'none',
          },
          '& blockquote': {
            borderLeft: '3px solid',
            borderColor: 'divider',
            margin: 0,
            paddingLeft: '1em',
            color: 'text.secondary',
            fontStyle: 'italic',
          },
          '& pre': {
            backgroundColor: 'action.hover',
            borderRadius: '6px',
            padding: '10px 12px',
            overflowX: 'auto',
            fontFamily:
              'ui-monospace, SFMono-Regular, Menlo, Consolas, monospace',
            fontSize: '0.8125rem',
            lineHeight: 1.5,
            '& code': {
              backgroundColor: 'transparent',
              padding: 0,
              fontSize: 'inherit',
            },
          },
          '& code': {
            backgroundColor: 'action.hover',
            borderRadius: '4px',
            padding: '0.1em 0.35em',
            fontFamily:
              'ui-monospace, SFMono-Regular, Menlo, Consolas, monospace',
            fontSize: '0.85em',
          },
        },
        ...slotProps?.root?.sx,
      }}
    >
      <Toolbar
        editor={editor}
        mode={mode}
        onModeChange={setMode}
        slotProps={{ root: slotProps?.toolbar }}
      />
      {mode === 'editor' ? (
        <EditorContent editor={editor} />
      ) : (
        <textarea
          disabled={disabled}
          value={value ?? ''}
          onChange={(e) => onChange?.(e.target.value)}
          {...slotProps?.textarea}
          style={{
            width: '100%',
            minHeight: 120,
            padding: '10px 14px',
            border: 'none',
            outline: 'none',
            resize: 'vertical',
            fontFamily: 'monospace',
            fontSize: '0.8125rem',
            lineHeight: 1.5,
            backgroundColor: 'transparent',
            color: 'inherit',
            boxSizing: 'border-box',
            display: 'block',
            ...slotProps?.textarea?.style,
          }}
        />
      )}
    </Div>
  );
}
