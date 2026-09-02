import {
  Dialog,
  DialogActions,
  DialogContent,
  Typography,
} from '@mui/material';

import { type ModalProps } from './types.d';
import { cloneElement, useImperativeHandle, useState } from 'react';
import { MdClose } from 'react-icons/md';

import { Div, Flex } from '@salvon/components/div';
import type { CloneableElement } from '@salvon/types';
import { isBoolean, isFunction, isString } from '@salvon/utils/type-check';
import { boolVal } from '@salvon/utils/type-transform';

export default function Modal({
  open,
  setOpen,
  anchor,
  children,
  slotProps,
  title,
  footer,
  maxWidth = 'sm',
  blur = true,
  backdrop = true,
  closeButton = false,
  closeOnEsc = false,
  closeOnBackdropClick = false,
  loading = false,
  canCloseOnLoading = false,
  keepMounted = false,
  ref,
}: ModalProps) {
  type SlotProps = typeof slotProps & { dialog?: any };
  const {
    wrapper,
    dialog,
    dialogContent,
    closeIcon,
    dialogActions,
  }: SlotProps = slotProps ?? {};

  const [internalControlledOpen, setInternalControlledOpen] =
    useState<boolean>(false);

  useImperativeHandle(ref, () => ({
    openModal,
    closeModal,
    open: openState,
  }));

  const openState = open ?? internalControlledOpen;
  const setOpenState = setOpen ?? setInternalControlledOpen;

  function openModal() {
    if (setOpenState) {
      setOpenState(true);
    }
  }

  function closeModal() {
    if (loading && !canCloseOnLoading) {
      return;
    }

    if (setOpenState) {
      setOpenState(false);
    }
  }

  const blurBackdropProps = {
    ...(blur && {
      backdropFilter: 'blur(3px)',
      backgroundColor: 'rgba(0,0,0,0.5)',
    }),
  };

  if (isFunction(children)) {
    children = children({ closeModal });
  }

  if (isFunction(footer)) {
    footer = footer({ closeModal });
  }

  return (
    <Div {...wrapper}>
      {anchor &&
        cloneElement(anchor as CloneableElement, {
          onClick: openModal,
        })}
      <Dialog
        fullWidth
        onClose={(_e, reason) => {
          if (reason === 'backdropClick' && closeOnBackdropClick) {
            closeModal();
          }

          if (reason === 'escapeKeyDown' && closeOnEsc) {
            closeModal();
          }
        }}
        {...dialog}
        keepMounted={keepMounted}
        maxWidth={maxWidth}
        open={openState}
        hideBackdrop={!backdrop}
        slotProps={{
          ...dialog?.slotProps,
          backdrop: {
            ...dialog?.slotProps?.backdrop,
            sx: {
              ...dialog?.slotProps?.backdrop?.sx,
              ...blurBackdropProps,
            },
          },
        }}
      >
        <DialogContent {...dialogContent}>
          <Flex
            fw
            jBetween={boolVal(title && closeButton)}
            jEnd={boolVal(!title && closeButton)}
            aCenter
          >
            {title && (
              <>
                {isString(title) ? (
                  <Typography
                    variant="h6"
                    sx={{
                      fontSize: '1.15rem',
                    }}
                  >
                    {title}
                  </Typography>
                ) : (
                  title
                )}
              </>
            )}
            {closeButton && (!loading || canCloseOnLoading) && (
              <>
                {isBoolean(closeButton) ? (
                  <MdClose
                    style={{ cursor: 'pointer' }}
                    size={20}
                    {...closeIcon}
                    onClick={closeModal}
                  />
                ) : (
                  closeButton
                )}
              </>
            )}
          </Flex>
          <Div>{children}</Div>
        </DialogContent>
        {footer && <DialogActions {...dialogActions}>{footer}</DialogActions>}
      </Dialog>
    </Div>
  );
}
