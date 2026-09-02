import {
  AddIconButton,
  BaseIconButton,
  CancelIconButton,
  CopyIconButton,
  CreateIconButton,
  DeleteIconButton,
  DownloadIconButton,
  EditIconButton,
  type IconButtonProps,
  OpenIconButton,
  PreviewIconButton,
  ReturnIconButton,
  SaveIconButton,
} from './index';
import { createElement } from 'react';

export type ButtonPreset =
  | 'add'
  | 'preview'
  | 'cancel'
  | 'create'
  | 'delete'
  | 'edit'
  | 'open'
  | 'return'
  | 'save'
  | 'download'
  | 'copy';

export type ButtonProps = IconButtonProps & {
  preset?: ButtonPreset;
};
export default function IconButton({ preset, ...props }: ButtonProps) {
  switch (preset) {
    case 'add': {
      return createElement(AddIconButton, props);
    }
    case 'preview': {
      return createElement(PreviewIconButton, props);
    }
    case 'cancel': {
      return createElement(CancelIconButton, props);
    }
    case 'create': {
      return createElement(CreateIconButton, props);
    }
    case 'delete': {
      return createElement(DeleteIconButton, props);
    }
    case 'edit': {
      return createElement(EditIconButton, props);
    }
    case 'open': {
      return createElement(OpenIconButton, props);
    }
    case 'return': {
      return createElement(ReturnIconButton, props);
    }
    case 'save': {
      return createElement(SaveIconButton, props);
    }
    case 'download': {
      return createElement(DownloadIconButton, props);
    }
    case 'copy': {
      return createElement(CopyIconButton, props);
    }
    default:
      return createElement(BaseIconButton, props);
  }
}
