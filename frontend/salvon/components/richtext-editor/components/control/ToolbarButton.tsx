import { IconButton, Tooltip } from '@mui/material';

import type { MouseEvent, ReactNode } from 'react';

export type ToolbarButtonProps = {
  label: string;
  active?: boolean;
  disabled?: boolean;
  onClick: (e: MouseEvent) => void;
  children: ReactNode;
};

export default function ToolbarButton({
  label,
  active = false,
  disabled = false,
  onClick,
  children,
}: ToolbarButtonProps) {
  return (
    <Tooltip title={label} placement="top" enterDelay={600}>
      <span>
        <IconButton
          size="small"
          disabled={disabled}
          onMouseDown={(e) => {
            e.preventDefault();
            onClick(e);
          }}
          sx={{
            borderRadius: '4px',
            padding: '4px',
            width: 28,
            height: 28,
            color: active ? 'primary.contrastText' : 'text.secondary',
            backgroundColor: active ? 'primary.main' : 'transparent',
            '& svg': { fontSize: '1.1rem' },
            '&:hover': {
              backgroundColor: active ? 'primary.dark' : 'action.hover',
              color: active ? 'primary.contrastText' : 'text.primary',
            },
            '&.Mui-disabled': {
              color: 'action.disabled',
              backgroundColor: 'transparent',
            },
          }}
        >
          {children}
        </IconButton>
      </span>
    </Tooltip>
  );
}
