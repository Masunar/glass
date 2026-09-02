import { Popover, TextField } from '@mui/material';
import type { Editor } from '@tiptap/react';

import ToolbarButton from './ToolbarButton';
import { useEffect, useRef, useState } from 'react';
import type { MouseEvent } from 'react';
import { MdLink, MdLinkOff } from 'react-icons/md';

import { Button } from '@salvon/components/button';
import { Flex } from '@salvon/components/div';

type LinkControlProps = {
  editor: Editor;
  disabled: boolean;
  label: string;
  removeLabel: string;
  placeholder: string;
  applyLabel: string;
};

export function LinkControl({
  editor,
  disabled,
  label,
  removeLabel,
  placeholder,
  applyLabel,
}: LinkControlProps) {
  const anchorRef = useRef<HTMLSpanElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);
  const [open, setOpen] = useState(false);
  const [url, setUrl] = useState('');
  const active = editor.isActive('link');

  useEffect(() => {
    if (open) requestAnimationFrame(() => inputRef.current?.focus());
  }, [open]);

  const openPopover = () => {
    setUrl(editor.getAttributes('link').href ?? '');
    setOpen(true);
  };

  const apply = () => {
    const href = url.trim();
    if (!href) {
      editor.chain().focus().extendMarkRange('link').unsetLink().run();
    } else if (editor.state.selection.empty && !active) {
      editor
        .chain()
        .focus()
        .insertContent({
          type: 'text',
          text: href,
          marks: [{ type: 'link', attrs: { href } }],
        })
        .run();
    } else {
      editor.chain().focus().extendMarkRange('link').setLink({ href }).run();
    }
    setOpen(false);
  };

  return (
    <>
      <span ref={anchorRef}>
        <ToolbarButton
          label={label}
          active={active}
          disabled={disabled}
          onClick={openPopover}
        >
          <MdLink />
        </ToolbarButton>
      </span>
      <ToolbarButton
        label={removeLabel}
        disabled={disabled || !active}
        onClick={() =>
          editor.chain().focus().extendMarkRange('link').unsetLink().run()
        }
      >
        <MdLinkOff />
      </ToolbarButton>
      <Popover
        open={open}
        anchorEl={anchorRef.current}
        onClose={() => setOpen(false)}
        anchorOrigin={{ vertical: 'bottom', horizontal: 'left' }}
        disableAutoFocus
        disableEnforceFocus
        disableRestoreFocus
      >
        <Flex sx={{ p: 1, gap: '8px', alignItems: 'center', width: 320 }}>
          <TextField
            inputRef={inputRef}
            size="small"
            fullWidth
            placeholder={placeholder}
            value={url}
            onChange={(e) => setUrl(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === 'Enter') {
                e.preventDefault();
                apply();
              }
            }}
          />
          <Button
            size="small"
            onMouseDown={(e: MouseEvent) => e.preventDefault()}
            onClick={apply}
          >
            {applyLabel}
          </Button>
        </Flex>
      </Popover>
    </>
  );
}
