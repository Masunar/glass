import {
  Popover as MUIPopover,
  type PopoverProps as MUIPopoverProps,
} from '@mui/material';

import { type Placement, placementConfiguration } from './placement';
import {
  type ForwardedRef,
  cloneElement,
  useImperativeHandle,
  useRef,
  useState,
} from 'react';

import type {
  AnchorElement,
  CallableChildren,
  ControllableOptionalState,
  Noop,
} from '@salvon/types';
import { isFunction } from '@salvon/utils/type-check';

export type PopoverRef = {
  openPopover: Noop;
  closePopover: () => void;
};

export type PopoverProps = Omit<
  MUIPopoverProps,
  'open' | 'children' | 'ref'
> & {
  onClose?: () => void;
  ref?: ForwardedRef<PopoverRef>;
  loading?: boolean;
  placement?: Placement;
} & ControllableOptionalState &
  CallableChildren<{ closePopover: () => void }> &
  AnchorElement;

export default function Popover({
  open,
  setOpen,
  anchor,
  onClose,
  anchorOrigin,
  transformOrigin,
  children,
  loading,
  ref,
  placement = 'bottom-center',
  ...props
}: PopoverProps) {
  const anchorRef = useRef<any>(null);
  const [internalControlledOpen, setInternalControlledOpen] =
    useState<boolean>(false);

  const openState = open ?? internalControlledOpen;
  const setOpenState = setOpen ?? setInternalControlledOpen;

  const openPopover = () => {
    setOpenState(true);
  };
  const closePopover = () => {
    if (loading) {
      return;
    }

    setOpenState(false);
  };

  useImperativeHandle(ref, () => ({
    openPopover,
    closePopover,
  }));

  if (isFunction(children)) {
    children = children({ closePopover });
  }

  if (anchor) {
    anchor = cloneElement(anchor, {
      ref: anchorRef,
      onClick: () => openPopover(),
    });
  }

  const placementPreset = placement ? placementConfiguration[placement] : {};

  return (
    <>
      {anchor}
      <MUIPopover
        anchorEl={anchorRef.current}
        transitionDuration={250}
        {...props}
        open={openState}
        anchorOrigin={anchorOrigin}
        transformOrigin={transformOrigin}
        {...placementPreset}
        onClose={() => {
          closePopover();
          if (onClose) {
            onClose();
          }
        }}
      >
        {children}
      </MUIPopover>
    </>
  );
}
