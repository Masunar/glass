import type { ReactNode } from 'react';
import type { Accept, FileRejection } from 'react-dropzone';

import type { DivProps } from '@salvon/components/div';
import type { SlotItem } from '@salvon/types';

export type DropzoneVariant = 'standard' | 'compact';

export type FileUploadProps = {
  onChange: (files: DropzoneFile[]) => void;
  onDropAccepted?: (accepted: File[]) => void;
  onDropRejected?: (rejected: FileRejection[]) => void;
  onFileTooLarge?: (files: File[]) => void;
  slotProps?: {
    wrapper: SlotItem<DivProps>;
  };
  variant?: DropzoneVariant;
  multiple?: boolean;
  showUploadedList?: boolean;
  disabled?: boolean;
  accept?: Accept | string[];
  mimeLabels?: Record<string, string>;
  maxSize?: number;
  translations?: Partial<DropzoneTranslations>;
  children?: ReactNode;
};

export type DropzoneTranslations = {
  prompt: ReactNode;
  browse: string;
  reject: ReactNode;
  tooLarge: ReactNode;
  formats: (extensions: string[]) => string;
  maxSize: (bytes: number) => string;
};

export type DropzoneFile = File & {
  preview: string | null;
  is_image: boolean;
  basename: string;
  extension: string;
};

export type StatefulFile = DropzoneFile & {
  progress: number;
};

export type DropzoneRenderProps = {
  files: StatefulFile[];
  isDragActive: boolean;
  isDragReject: boolean;
  tooLarge: boolean;
  disabled?: boolean;
  formatsHint?: string;
  maxSizeHint?: string;
  t: DropzoneTranslations;
  // react-dropzone prop getters — untyped at the lib seam
  getRootProps: (...args: any[]) => any;
  getInputProps: (...args: any[]) => any;
  onRemove: (index: number) => void;
};
