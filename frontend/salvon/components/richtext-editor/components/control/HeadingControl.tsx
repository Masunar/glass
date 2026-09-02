import { IconButton, Popover, Tooltip } from '@mui/material';
import type { Editor } from '@tiptap/react';

import { useRef, useState } from 'react';
import type { MouseEvent } from 'react';
import { MdKeyboardArrowDown } from 'react-icons/md';

import { Div, Flex } from '@salvon/components/div';

type HeadingControlProps = {
  editor: Editor;
  disabled: boolean;
  label: string;
  paragraphLabel: string;
  headingLabel: (level: number) => string;
  currentLevel: number;
};

export function HeadingControl({
  editor,
  disabled,
  label,
  paragraphLabel,
  headingLabel,
  currentLevel,
}: HeadingControlProps) {
  const anchorRef = useRef<HTMLSpanElement>(null);
  const [open, setOpen] = useState(false);

  const apply = (level: number) => {
    if (level === 0) editor.chain().focus().setParagraph().run();
    else
      editor
        .chain()
        .focus()
        .setHeading({ level: level as 1 | 2 | 3 | 4 | 5 | 6 })
        .run();
    setOpen(false);
  };

  return (
    <>
      <span ref={anchorRef}>
        <Tooltip title={label} placement="top" enterDelay={600}>
          <span>
            <IconButton
              size="small"
              disabled={disabled}
              onMouseDown={(e) => {
                e.preventDefault();
                setOpen(true);
              }}
              sx={{
                borderRadius: '4px',
                px: '4px',
                height: 28,
                minWidth: 34,
                color: currentLevel ? 'primary.contrastText' : 'text.secondary',
                backgroundColor: currentLevel ? 'primary.main' : 'transparent',
                '&:hover': {
                  backgroundColor: currentLevel
                    ? 'primary.dark'
                    : 'action.hover',
                  color: currentLevel ? 'primary.contrastText' : 'text.primary',
                },
                '&.Mui-disabled': { color: 'action.disabled' },
              }}
            >
              <span style={{ fontWeight: 700, fontSize: '0.8rem' }}>
                H{currentLevel || ''}
              </span>
              <MdKeyboardArrowDown style={{ fontSize: '0.9rem' }} />
            </IconButton>
          </span>
        </Tooltip>
      </span>
      <Popover
        open={open}
        anchorEl={anchorRef.current}
        onClose={() => setOpen(false)}
        anchorOrigin={{ vertical: 'bottom', horizontal: 'left' }}
        disableAutoFocus
        disableEnforceFocus
      >
        <Div sx={{ py: 0.5, minWidth: 160 }}>
          {[0, 1, 2, 3, 4, 5, 6].map((level) => {
            const active = currentLevel === level;
            return (
              <Flex
                key={level}
                onMouseDown={(e: MouseEvent) => {
                  e.preventDefault();
                  apply(level);
                }}
                sx={{
                  alignItems: 'center',
                  gap: '10px',
                  px: 1.5,
                  py: 0.75,
                  cursor: 'pointer',
                  '&:hover': { backgroundColor: 'action.hover' },
                }}
              >
                <Div
                  sx={{
                    width: 22,
                    fontWeight: 700,
                    fontSize:
                      level === 0 ? '0.7rem' : `${1.15 - level * 0.06}rem`,
                    color: active ? 'primary.main' : 'text.secondary',
                  }}
                >
                  {level === 0 ? '¶' : `H${level}`}
                </Div>
                <Div
                  sx={{
                    fontSize: '0.8125rem',
                    fontWeight: active ? 700 : 400,
                    color: active ? 'primary.main' : 'text.primary',
                  }}
                >
                  {level === 0 ? paragraphLabel : headingLabel(level)}
                </Div>
              </Flex>
            );
          })}
        </Div>
      </Popover>
    </>
  );
}
