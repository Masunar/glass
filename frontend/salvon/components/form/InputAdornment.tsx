import {
  InputAdornment as MUIInputAdornment,
  type InputAdornmentProps as MUIInputAdornmentProps,
} from '@mui/material';

import type { ReactNode } from 'react';

import {
  IconButton,
  type IconButtonProps,
} from '@salvon/components/icon-button';

export type InputAdornmentProps = Omit<
  MUIInputAdornmentProps,
  'children' | 'position'
> & {
  position?: MUIInputAdornmentProps['position'];
  icon?: ReactNode;
  label?: IconButtonProps['label'];
  onClick?: IconButtonProps['onClick'];
  disabled?: boolean;
  children?: ReactNode;
  slotProps?: {
    button?: Partial<IconButtonProps>;
  };
};

export default function InputAdornment({
  icon,
  label,
  onClick,
  disabled,
  children,
  slotProps,
  position = 'end',
  ...props
}: InputAdornmentProps) {
  return (
    <MUIInputAdornment position={position} {...props}>
      {children ?? (
        <IconButton
          variant="mui"
          size="small"
          disableRipple
          icon={icon}
          label={label}
          onClick={onClick}
          disabled={disabled}
          sx={{ '&:hover': { backgroundColor: 'transparent' } }}
          {...slotProps?.button}
        />
      )}
    </MUIInputAdornment>
  );
}
