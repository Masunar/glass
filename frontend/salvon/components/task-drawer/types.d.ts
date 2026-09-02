import type {
  DrawerProps as MUIDrawerProps,
  PaperProps as MUIPaperProps,
} from '@mui/material';

import type { Dayjs } from 'dayjs';
import type { ForwardedRef, ReactNode } from 'react';

import type { FlexProps } from '@salvon/components/div';
import type { SelectOption } from '@salvon/components/select';
import type {
  ControllableOptionalState,
  SetState,
  SlotItem,
} from '@salvon/types';

export type TaskDrawerSaveResult = boolean | void;

export type TaskDrawerEditorProps<V = any> = {
  value: V;
  setValue: (value: V) => void;
  save: () => void;
  cancel: () => void;
  loading: boolean;
};

type BaseField = {
  key: string;
  label: ReactNode;
  icon?: ReactNode;
  value?: any;
  render?: (value: any) => ReactNode;
  placeholder?: ReactNode;
  editable?: boolean;
  onSave?: (value: any) => TaskDrawerSaveResult | Promise<TaskDrawerSaveResult>;
};

export type TaskDrawerField =
  | (BaseField & { type?: 'text' | 'textarea' })
  | (BaseField & { type: 'date' | 'datetime'; value?: Dayjs | null })
  | (BaseField & {
      type: 'select';
      options: Array<SelectOption<string | number>>;
    })
  | (BaseField & {
      type: 'custom';
      renderEditor: (props: TaskDrawerEditorProps) => ReactNode;
    })
  | (BaseField & { type: 'static'; editable?: false });

export type TaskDrawerSlotProps = {
  paper?: SlotItem<MUIPaperProps>;
  root?: SlotItem<FlexProps>;
  header?: SlotItem<FlexProps>;
  body?: SlotItem<FlexProps>;
  fields?: SlotItem<FlexProps>;
};

export type TaskDrawerRef = ForwardedRef<{
  openDrawer: () => void;
  closeDrawer: () => void;
  open: boolean;
}>;

export type TaskDrawerTitle = {
  title: string;
  subtitle?: ReactNode;
  editable?: boolean;
  onSave?: (
    value: string,
  ) => TaskDrawerSaveResult | Promise<TaskDrawerSaveResult>;
};

export type TaskDrawerProps = {
  open: boolean;
  setOpen: SetState<boolean>;
  onExited?: () => void;
  closeOnClickAway?: boolean;

  titleIcon?: ReactNode;
  title?: TaskDrawerTitle;
  headerActions?: ReactNode;

  fields?: TaskDrawerField[];

  children?: ReactNode;
  footer?: ReactNode;

  side?: MUIDrawerProps['anchor'];
  width?: number | string;
  slotProps?: TaskDrawerSlotProps;
  ref?: TaskDrawerRef;
};

export type TaskDrawerSectionProps = {
  title: ReactNode;
  icon?: ReactNode;
  defaultOpen?: boolean;
  loading?: boolean;
  action?: ReactNode;
  children: ReactNode;
  slotProps?: {
    header?: SlotItem<FlexProps>;
    body?: SlotItem<FlexProps>;
  };
} & ControllableOptionalState;

export type TaskDrawerDescriptionProps = {
  value?: string;
  editable?: boolean;
  onSave?: (
    value: string,
  ) => TaskDrawerSaveResult | Promise<TaskDrawerSaveResult>;
  title?: ReactNode;
  icon?: ReactNode;
  placeholder?: ReactNode;
  defaultOpen?: boolean;
  renderValue?: (value: string) => ReactNode;
  renderEditor?: (props: TaskDrawerEditorProps<string>) => ReactNode;
} & ControllableOptionalState;

export type TaskDrawerComment = {
  id: string;
  author: string;
  avatar?: ReactNode;
  date?: string | number | Dayjs;
  content: ReactNode;
};

export type TaskDrawerCommentsProps = {
  items?: TaskDrawerComment[];
  loading?: boolean;
  submitting?: boolean;
  title?: ReactNode;
  icon?: ReactNode;
  defaultOpen?: boolean;
  placeholder?: string;
  emptyText?: ReactNode;
  onSubmit?: (
    value: string,
  ) => TaskDrawerSaveResult | Promise<TaskDrawerSaveResult>;
  onDelete?: (id: string) => void | Promise<void>;
} & ControllableOptionalState;

export type TaskDrawerActivityEntry = {
  id: string;
  actor?: string;
  avatar?: ReactNode;
  date?: string | number | Dayjs;
  description: ReactNode;
};

export type TaskDrawerActivityProps = {
  items?: TaskDrawerActivityEntry[];
  loading?: boolean;
  title?: ReactNode;
  icon?: ReactNode;
  defaultOpen?: boolean;
  emptyText?: ReactNode;
} & ControllableOptionalState;

export type TaskDrawerMessageBoxProps = {
  onSend: (
    value: string,
  ) => TaskDrawerSaveResult | Promise<TaskDrawerSaveResult>;
  placeholder?: string;
  avatar?: ReactNode;
  authorInitial?: string;
  loading?: boolean;
  disabled?: boolean;
};

export type TaskDrawerActionsProps = {
  comments?: TaskDrawerComment[];
  activity?: TaskDrawerActivityEntry[];
  commentsLoading?: boolean;
  activityLoading?: boolean;
  onDeleteComment?: (id: string) => void | Promise<void>;
  title?: ReactNode;
  icon?: ReactNode;
  defaultOpen?: boolean;
  showActivity?: boolean;
  commentsLabel?: string;
  activityLabel?: string;
  commentsEmptyText?: ReactNode;
  activityEmptyText?: ReactNode;
} & ControllableOptionalState;
