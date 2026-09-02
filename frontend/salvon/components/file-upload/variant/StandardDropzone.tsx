import { Typography } from '@mui/material';

import BaseDropzone from '../BaseDropzone';
import DropzonePrompt from '../components/DropzonePrompt';
import FileList from '../components/FileList';
import type { FileUploadProps } from '../types';

import { Div, Flex } from '@salvon/components/div';
import { usePalette } from '@salvon/hooks/useTheme';

export default function StandardDropzone(props: FileUploadProps) {
  const dz = usePalette()?.salvon?.file_upload?.dropzone;
  const { slotProps, children } = props;
  const wrapper = slotProps?.wrapper;

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

        return (
          <>
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
                  padding: '28px 16px',
                  textAlign: 'center',
                  backgroundColor: isDragActive ? dz?.bgActive : dz?.bg,
                  transition:
                    'background-color 0.15s ease, border-color 0.15s ease',
                  opacity: disabled ? 0.6 : 1,
                  ...(wrapper?.sx ?? {}),
                }}
              >
                <input {...getInputProps()} />
                <DropzonePrompt
                  prompt={t.prompt}
                  browse={t.browse}
                  reject={t.reject}
                  tooLarge={t.tooLarge}
                  isReject={isDragActive && isDragReject}
                  isTooLarge={tooLarge}
                >
                  {children}
                </DropzonePrompt>
              </Div>
            </div>

            {(formatsHint || maxSizeHint) && (
              <Flex
                jBetween
                aCenter
                mt={1}
                sx={{ px: '2px', color: dz?.hintColor ?? 'text.disabled' }}
              >
                <Typography variant="caption" sx={{ color: 'inherit' }}>
                  {formatsHint}
                </Typography>
                <Typography variant="caption" sx={{ color: 'inherit' }}>
                  {maxSizeHint}
                </Typography>
              </Flex>
            )}
          </>
        );
      }}
      renderFiles={({ files, onRemove }) => (
        <FileList files={files} onRemove={onRemove} />
      )}
    />
  );
}
