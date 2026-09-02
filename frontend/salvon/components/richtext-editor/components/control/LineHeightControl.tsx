import { Popover } from '@mui/material';
import type { Editor } from '@tiptap/react';

import ToolbarButton from './ToolbarButton';
import { LINE_HEIGHTS } from './constants';
import { useRef, useState } from 'react';
import type { MouseEvent } from 'react';
import { MdFormatLineSpacing } from 'react-icons/md';

import { Div } from '@salvon/components/div';

type LineHeightControlProps = {
  editor: Editor;
  disabled: boolean;
  label: string;
  defaultLabel: string;
  current?: string;
};

export function LineHeightControl({
  editor,
  disabled,
  label,
  defaultLabel,
  current,
}: LineHeightControlProps) {
  const anchorRef = useRef<HTMLSpanElement>(null);
  const [open, setOpen] = useState(false);

  return (
    <>
      <span ref={anchorRef}>
        <ToolbarButton
          label={label}
          active={!!current}
          disabled={disabled}
          onClick={() => setOpen(true)}
        >
          <MdFormatLineSpacing />
        </ToolbarButton>
      </span>
      <Popover
        open={open}
        anchorEl={anchorRef.current}
        onClose={() => setOpen(false)}
        anchorOrigin={{ vertical: 'bottom', horizontal: 'left' }}
        disableAutoFocus
        disableEnforceFocus
      >
        <Div sx={{ py: 0.5, minWidth: 96 }}>
          <Div
            onMouseDown={(e: MouseEvent) => {
              e.preventDefault();
              editor.chain().focus().unsetLineHeight().run();
              setOpen(false);
            }}
            sx={{
              px: 1.5,
              py: 0.75,
              cursor: 'pointer',
              fontSize: '0.8125rem',
              fontWeight: !current ? 700 : 400,
              '&:hover': { backgroundColor: 'action.hover' },
            }}
          >
            {defaultLabel}
          </Div>
          {LINE_HEIGHTS.map((lh) => (
            <Div
              key={lh}
              onMouseDown={(e: MouseEvent) => {
                e.preventDefault();
                editor.chain().focus().setLineHeight(lh).run();
                setOpen(false);
              }}
              sx={{
                px: 1.5,
                py: 0.75,
                cursor: 'pointer',
                fontSize: '0.8125rem',
                fontWeight: current === lh ? 700 : 400,
                color: current === lh ? 'primary.main' : 'text.primary',
                '&:hover': { backgroundColor: 'action.hover' },
              }}
            >
              {lh}
            </Div>
          ))}
        </Div>
      </Popover>
    </>
  );
}
