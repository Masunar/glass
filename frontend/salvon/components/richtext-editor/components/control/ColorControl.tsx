import { Popover } from '@mui/material';
import type { Editor } from '@tiptap/react';

import ToolbarButton from './ToolbarButton';
import { useRef, useState } from 'react';
import type { MouseEvent, ReactNode } from 'react';

import { Button } from '@salvon/components/button';
import { Div, Flex } from '@salvon/components/div';

type ColorControlProps = {
  editor: Editor;
  label: string;
  clearLabel: string;
  disabled: boolean;
  icon: ReactNode;
  colors: string[];
  active: boolean;
  current?: string;
  onApply: (color: string) => void;
  onClear: () => void;
};

export function ColorControl({
  label,
  clearLabel,
  disabled,
  icon,
  colors,
  active,
  current,
  onApply,
  onClear,
}: ColorControlProps) {
  const anchorRef = useRef<HTMLSpanElement>(null);
  const [open, setOpen] = useState(false);

  return (
    <>
      <span ref={anchorRef}>
        <ToolbarButton
          label={label}
          active={active}
          disabled={disabled}
          onClick={() => setOpen(true)}
        >
          {icon}
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
        <Div sx={{ p: 1, width: 168 }}>
          <Flex sx={{ flexWrap: 'wrap', gap: '6px' }}>
            {colors.map((c) => (
              <Div
                key={c}
                onMouseDown={(e) => {
                  e.preventDefault();
                  onApply(c);
                  setOpen(false);
                }}
                sx={{
                  width: 24,
                  height: 24,
                  borderRadius: '4px',
                  cursor: 'pointer',
                  backgroundColor: c,
                  border: '1px solid',
                  borderColor: 'divider',
                  outline: current === c ? '2px solid' : 'none',
                  outlineColor: 'primary.main',
                  outlineOffset: '1px',
                }}
              />
            ))}
          </Flex>
          <Flex sx={{ alignItems: 'center', gap: '8px', mt: 1 }}>
            <input
              type="color"
              value={current ?? '#000000'}
              onChange={(e) => onApply(e.target.value)}
              style={{
                width: 28,
                height: 28,
                padding: 0,
                border: 'none',
                background: 'none',
                cursor: 'pointer',
              }}
            />
            <Button
              variant="text"
              size="small"
              onMouseDown={(e: MouseEvent) => {
                e.preventDefault();
                onClear();
                setOpen(false);
              }}
            >
              {clearLabel}
            </Button>
          </Flex>
        </Div>
      </Popover>
    </>
  );
}
