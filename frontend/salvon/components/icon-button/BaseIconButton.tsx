import {
  IconButton as MUIIconButton,
  type IconButtonProps as MUIIconButtonProps,
  Tooltip,
  type TooltipProps,
  styled,
} from '@mui/material';

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

export type IconButtonVariant = 'mui' | 'square' | 'square-solid';

export type IconButtonProps = {
  path?: GeneratePathUrl;
  pathParams?: GeneratePathParams;
  icon?: ReactNode;
  children?: ReactNode;
  label?: string | ReactNode;
  variant?: IconButtonVariant;
  slotProps?: {
    tooltip?: SlotItem<TooltipProps>;
    link?: SlotItem<LinkProps>;
  };
  confirm?: ConfirmPopoverProps;
} & MUIIconButtonProps;

const StyledIconButton = styled(MUIIconButton, {
  shouldForwardProp: (prop) => prop !== 'buttonVariant',
})<{ buttonVariant?: IconButtonVariant }>(({ theme, buttonVariant, color }) => {
  const hasColor = color && color !== 'default' && color !== 'inherit';
  const paletteColor =
    hasColor && color in theme.palette
      ? theme.palette[color as keyof typeof theme.palette]
      : undefined;

  const squareBase = {
    width: 36,
    height: 36,
    borderRadius: '9px',
    '& svg': { fontSize: '1.15rem' },
  };

  if (
    buttonVariant === 'square-solid' &&
    paletteColor &&
    //@ts-ignore
    'main' in paletteColor
  ) {
    return {
      ...squareBase,
      border: `1px solid ${paletteColor.main}`,
      background: paletteColor.main,
      color: paletteColor.contrastText,
      '&:hover': {
        background: paletteColor.dark,
        borderColor: paletteColor.dark,
        color: paletteColor.contrastText,
      },
    };
  }

  if (['square', 'square-solid'].includes(buttonVariant ?? '')) {
    return {
      ...squareBase,
      border: `1px solid ${theme.palette.divider}`,
      background: 'transparent',
      // Don't force the default gray when an explicit color prop is set.
      ...(hasColor ? {} : { color: theme.palette.text.secondary }),
      '&:hover': {
        background: theme.palette.action.hover,
        borderColor: theme.palette.action.hover,
        ...(hasColor ? {} : { color: theme.palette.text.primary }),
      },
    };
  }

  return {};
});

export default function BaseIconButton({
  icon,
  children,
  label,
  variant = 'square',
  slotProps,
  path,
  confirm,
  pathParams = {},
  ...props
}: IconButtonProps) {
  const { tooltip, link } = slotProps ?? {};

  let button = (
    <StyledIconButton
      buttonVariant={variant}
      disableRipple={['square', 'square-solid'].includes(variant)}
      {...props}
    >
      {!!icon && icon} {!!children && children}
    </StyledIconButton>
  );

  if (label) {
    button = (
      <Tooltip {...tooltip} title={label}>
        {button}
      </Tooltip>
    );
  }

  if (path) {
    button = (
      <Link {...link} path={path} pathParams={pathParams}>
        {button}
      </Link>
    );
  }

  if (confirm) {
    button = <ConfirmPopover {...confirm} anchor={button} />;
  }

  return button;
}
