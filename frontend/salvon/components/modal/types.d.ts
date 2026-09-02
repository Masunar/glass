import type {
  Breakpoint,
  ButtonProps,
  DialogActionsProps,
  DialogContentProps,
  DialogProps,
} from '@mui/material';

import type { ForwardedRef, MouseEvent, ReactElement, ReactNode } from 'react';
import type { SubmitErrorHandler, UseFormProps } from 'react-hook-form';
import type { IconType } from 'react-icons';

import type { DivProps, FlexProps } from '@salvon/components/div';
import type { FormProps } from '@salvon/components/form';
import {
  type AnchorElement,
  type CallableChildren,
  type CallableNode,
  type ControllableOptionalState,
  type Noop,
  type SlotItem,
} from '@salvon/types';

export type ModalElementCallbackProps = {
  closeModal: () => void;
};

export type ModalRef = {
  open?: boolean;
  openModal: () => void;
  closeModal: () => void;
};

export type ModalProps = ControllableOptionalState & {
  title?: ReactNode;
  blur?: boolean;
  backdrop?: boolean;
  closeButton?: boolean | ReactNode;
  closeOnEsc?: boolean;
  loading?: boolean;
  canCloseOnLoading?: boolean;
  closeOnBackdropClick?: boolean;
  maxWidth?: Breakpoint | false;
  slotProps?: {
    wrapper?: SlotItem<DivProps>;
    dialog?: SlotItem<DialogProps>;
    dialogContent?: SlotItem<DialogContentProps>;
    closeIcon?: SlotItem<IconType>;
    dialogActions?: SlotItem<DialogActionsProps>;
  };
  footer?: CallableNode<ModalElementCallbackProps>;
  ref?: ForwardedRef<ModalRef>;
  keepMounted?: boolean;
} & AnchorElement &
  Partial<CallableChildren<ModalElementCallbackProps>>;

export type ConfirmActionCallbackProps = {
  closeModal: Noop;
  event: MouseEvent<HTMLElement>;
};

export type ExtendableModalProps = {
  children?: ReactNode;
  maxWidth?: Breakpoint | false;
  slotProps?: {
    modal?: SlotItem<ModalProps>;
  };
  title?: ReactNode;
  closeButton?: boolean | ReactNode;
  blur?: boolean;
  backdrop?: boolean;
  keepMounted?: boolean;
} & ControllableOptionalState &
  AnchorElement;

export type CancelableModalProps = ExtendableModalProps & {
  slotProps?: {
    modal?: SlotItem<ModalProps>;
    footerWrapper?: SlotItem<FlexProps>;
  };
  cancelLabel?: string;
  onCancel?: (props: ConfirmActionCallbackProps) => void;
  autoCloseOnCancel?: boolean;
};

export type ConfirmableModalProps = CancelableModalProps & {
  onConfirm?: (props: ConfirmActionCallbackProps) => void;
  confirmLabel?: string;
  autoCloseOnConfirm?: boolean;
  loading?: boolean;
};

export type ConfirmModalProps = ConfirmableModalProps & {
  cancelButton?: ReactElement<ButtonProps>;
  confirmButton?: ReactElement<ButtonProps>;
};

type WithLoadingButtonMask = {
  loadingButtonMask?: 'loader' | 'disabled';
};

export type ActionModalProps = ConfirmableModalProps & {
  cancelButton?: ReactElement<ButtonProps> | false;
  confirmButton?: ReactElement<ButtonProps> | false;
  deleteButton?: ReactElement<ButtonProps> | false;
  onDelete?: (props: ConfirmActionCallbackProps) => void;
  autoCloseOnDelete?: boolean;
  deleteLabel?: string;
  variant?: 'confirm' | 'delete' | 'info';
} & WithLoadingButtonMask;

type CancelableFormModal = CancelableModalProps & FormProps;

export type FormModalProps = CancelableFormModal & {
  onSubmit?: (data: any) => void;
  submitLabel?: string;
  submitButton?: ReactElement<ButtonProps>;
  loading?: boolean;
  cancelButton?: ReactElement<ButtonProps>;
  onValidationFailed?: SubmitErrorHandler<any>;
  useFormProps?: UseFormProps;
  footer?:
    ReactNode | ((props: ModalElementCallbackProps) => ReactNode) | false;
} & WithLoadingButtonMask;
