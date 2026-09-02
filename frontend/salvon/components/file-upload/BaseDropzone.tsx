import type {
  DropzoneFile,
  DropzoneRenderProps,
  FileUploadProps,
  StatefulFile,
} from './types';
import {
  DEFAULT_TRANSLATIONS,
  deriveExtensions,
  normalizeAccept,
} from './utils';
import type { ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';
import ReactDropzone from 'react-dropzone';

import { fileSplitName } from '@salvon/utils/file';

type BaseDropzoneProps = FileUploadProps & {
  renderZone: (props: DropzoneRenderProps) => ReactNode;
  renderFiles: (props: DropzoneRenderProps) => ReactNode;
};

export default function BaseDropzone({
  accept,
  onDropAccepted,
  onDropRejected,
  onFileTooLarge,
  onChange,
  multiple,
  disabled,
  maxSize,
  mimeLabels,
  translations,
  showUploadedList = true,
  renderZone,
  renderFiles,
}: BaseDropzoneProps) {
  const [files, setFiles] = useState<StatefulFile[]>([]);
  const [tooLarge, setTooLarge] = useState(false);
  const tooLargeTimer = useRef<ReturnType<typeof setTimeout>>(undefined);

  const flagTooLarge = () => {
    setTooLarge(true);
    clearTimeout(tooLargeTimer.current);
    tooLargeTimer.current = setTimeout(() => setTooLarge(false), 3000);
  };

  const clearTooLarge = () => {
    clearTimeout(tooLargeTimer.current);
    setTooLarge(false);
  };

  const t = { ...DEFAULT_TRANSLATIONS, ...translations };
  const extensions = deriveExtensions(accept, mimeLabels);
  const acceptMap = normalizeAccept(accept);

  const formatsHint = extensions.length ? t.formats(extensions) : undefined;
  const maxSizeHint = maxSize ? t.maxSize(maxSize) : undefined;

  const readWithProgress = (file: File) => {
    const reader = new FileReader();
    const setProgress = (progress: number) =>
      setFiles((prev) =>
        prev.map((f) => (f === file ? Object.assign(f, { progress }) : f)),
      );
    reader.onprogress = (e) => {
      if (e.lengthComputable) {
        setProgress(Math.round((e.loaded / e.total) * 100));
      }
    };
    reader.onloadend = () => setProgress(100);
    reader.readAsArrayBuffer(file);
  };

  const removeFile = (index: number) => {
    const removed = files[index];
    if (removed?.preview) {
      URL.revokeObjectURL(removed.preview);
    }
    const reduced = files.filter((_, i) => i !== index);
    onChange(reduced as DropzoneFile[]);
    setFiles(reduced);
  };

  useEffect(() => {
    return () => {
      clearTimeout(tooLargeTimer.current);
      files.forEach((f) => {
        if (f.preview !== null) {
          URL.revokeObjectURL(f.preview);
        }
      });
    };
  }, []);

  return (
    <ReactDropzone
      disabled={disabled}
      accept={acceptMap}
      multiple={multiple}
      maxSize={maxSize}
      onDragEnter={clearTooLarge}
      onDropAccepted={(accepted) => {
        const acceptedWithPreview = accepted.map((file) => {
          const isImage = file.type.startsWith('image/');
          const { name, extension } = fileSplitName(file.name);
          return Object.assign(file, {
            preview: isImage ? URL.createObjectURL(file) : null,
            is_image: isImage,
            progress: 0,
            basename: name,
            extension,
          });
        }) as StatefulFile[];

        const allFiles = multiple
          ? [...files, ...acceptedWithPreview]
          : acceptedWithPreview;

        onChange(allFiles as DropzoneFile[]);
        onDropAccepted?.(acceptedWithPreview);

        setFiles(allFiles);
        acceptedWithPreview.forEach(readWithProgress);
      }}
      onDropRejected={(rejected) => {
        const tooLargeFiles = rejected
          .filter((r) => r.errors.some((e) => e.code === 'file-too-large'))
          .map((r) => r.file);
        if (tooLargeFiles.length) {
          flagTooLarge();
          onFileTooLarge?.(tooLargeFiles);
        }
        onDropRejected?.(rejected);
      }}
    >
      {({ getRootProps, getInputProps, isDragActive, isDragReject }) => {
        const renderProps: DropzoneRenderProps = {
          files,
          isDragActive,
          isDragReject,
          tooLarge,
          disabled,
          formatsHint,
          maxSizeHint,
          t,
          getRootProps,
          getInputProps,
          onRemove: removeFile,
        };

        return (
          <div>
            {renderZone(renderProps)}
            {showUploadedList && renderFiles(renderProps)}
          </div>
        );
      }}
    </ReactDropzone>
  );
}
