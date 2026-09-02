import { type ButtonProps as MUIBaseButtonProps } from '@mui/material';
import { Button as MUIButton } from '@mui/material';

import { type ReactNode } from 'react';

import { Link, type LinkProps } from '@salvon/components/navigation';
import ConfirmPopover, {
  type ConfirmPopoverProps,
} from '@salvon/components/popover/ConfirmPopover';
import type { SlotItem } from '@salvon/types';
import {
  type GeneratePathParams,
  type GeneratePathUrl,
} from '@salvon/utils/generate-path';

export type BaseButtonProps = Omit<MUIBaseButtonProps, 'href'> & {
  path?: GeneratePathUrl;
  pathParams?: GeneratePathParams;
  icon?: ReactNode;
  slotProps?: {
    link?: SlotItem<LinkProps>;
  };
  confirm?: ConfirmPopoverProps;
};

export default function BaseButton({
  path,
  sx,
  icon,
  children,
  slotProps,
  loading,
  confirm,
  fullWidth,
  pathParams = {},
  ...props
}: BaseButtonProps) {
  const { link = {} } = slotProps ?? {};

  if (props.disabled) {
    path = undefined;
  }

  let button = (
    <MUIButton
      disableElevation
      variant="contained"
      loading={loading}
      type="button"
      {...props}
      fullWidth={fullWidth}
      sx={{ textTransform: 'none', ...sx }}
      startIcon={loading ? undefined : icon}
    >
      {children}
    </MUIButton>
  );

  if (path) {
    button = (
      <Link
        {...link}
        path={path}
        pathParams={pathParams}
        sx={{ width: fullWidth ? '100%' : 'auto', ...(link?.sx ?? {}) }}
      >
        {button}
      </Link>
    );
  }

  if (confirm) {
    button = <ConfirmPopover {...confirm} anchor={button} />;
  }

  return button;
}
