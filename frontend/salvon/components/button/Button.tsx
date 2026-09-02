import {
  AddButton,
  BaseButton,
  type BaseButtonProps,
  CancelButton,
  CloseButton,
  CopyButton,
  CreateButton,
  DeleteButton,
  DownloadButton,
  EditButton,
  OpenButton,
  ReturnButton,
  SaveButton,
} from './index';
import { createElement } from 'react';

export type ButtonPreset =
  | 'add'
  | 'cancel'
  | 'create'
  | 'delete'
  | 'edit'
  | 'open'
  | 'return'
  | 'save'
  | 'close'
  | 'download'
  | 'copy';

export type ButtonProps = BaseButtonProps & {
  preset?: ButtonPreset;
};
export default function Button({ preset, ...props }: ButtonProps) {
  switch (preset) {
    case 'add': {
      return createElement(AddButton, props);
    }
    case 'cancel': {
      return createElement(CancelButton, props);
    }
    case 'create': {
      return createElement(CreateButton, props);
    }
    case 'delete': {
      return createElement(DeleteButton, props);
    }
    case 'edit': {
      return createElement(EditButton, props);
    }
    case 'open': {
      return createElement(OpenButton, props);
    }
    case 'return': {
      return createElement(ReturnButton, props);
    }
    case 'save': {
      return createElement(SaveButton, props);
    }
    case 'close': {
      return createElement(CloseButton, props);
    }
    case 'download': {
      return createElement(DownloadButton, props);
    }
    case 'copy': {
      return createElement(CopyButton, props);
    }
    default:
      return createElement(BaseButton, props);
  }
}
