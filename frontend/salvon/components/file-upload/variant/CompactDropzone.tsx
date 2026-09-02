import BaseDropzone from '../BaseDropzone';
import CompactZone from '../components/CompactZone';
import FilePillList from '../components/FilePillList';
import type { FileUploadProps } from '../types';

import { Div } from '@salvon/components/div';
import { usePalette } from '@salvon/hooks/useTheme';

export default function CompactDropzone(props: FileUploadProps) {
  const dz = usePalette()?.salvon?.file_upload?.dropzone;
  const wrapper = props.slotProps?.wrapper;

  return (
    <BaseDropzone
      {...props}
      renderZone={({
        getRootProps,
        getInputProps,
        isDragActive,
        isDragReject,
        tooLarge,
        disabled,
        formatsHint,
        maxSizeHint,
        t,
      }) => {
        const border = dz?.borderColor;
        const borderColor = isDragActive
          ? isDragReject
            ? border?.reject
            : border?.accept
          : border?.default;

        const hint = [formatsHint, maxSizeHint].filter(Boolean).join(' · ');
        const title =
          isDragActive && isDragReject ? (
            t.reject
          ) : tooLarge ? (
            t.tooLarge
          ) : (
            <>
              {t.prompt}
              {t.browse}
            </>
          );

        return (
          <div
            className="file-dropzone-root"
            {...getRootProps()}
            style={{ cursor: disabled ? 'not-allowed' : 'pointer' }}
          >
            <Div
              {...(wrapper ?? {})}
              sx={{
                width: '100%',
                border: `1.5px dashed ${borderColor ?? '#c7d0e0'}`,
                borderRadius: '12px',
                padding: '14px 16px',
                backgroundColor: isDragActive ? dz?.bgActive : dz?.bg,
                transition:
                  'background-color 0.15s ease, border-color 0.15s ease',
                opacity: disabled ? 0.6 : 1,
                ...(wrapper?.sx ?? {}),
              }}
            >
              <input {...getInputProps()} />
              <CompactZone title={title} hint={hint || undefined}>
                {props.children}
              </CompactZone>
            </Div>
          </div>
        );
      }}
      renderFiles={({ files, onRemove }) => (
        <FilePillList files={files} onRemove={onRemove} />
      )}
    />
  );
}
