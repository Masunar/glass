import { Typography } from '@mui/material';

import type { StatefulFile } from '../types';
import { FiX } from 'react-icons/fi';

import { Div, Flex } from '@salvon/components/div';
import { usePalette } from '@salvon/hooks/useTheme';
import { fileSplitName, formatFileSize } from '@salvon/utils/file';

export type FileRowProps = {
  file: StatefulFile;
  onRemove: () => void;
};

export default function FileRow({ file, onRemove }: FileRowProps) {
  const fu = usePalette()?.salvon?.file_upload;
  const card = fu?.fileCard;
  const bar = fu?.progress;

  return (
    <Flex
      column
      gap={1}
      sx={{
        backgroundColor: card?.bg ?? 'action.hover',
        borderRadius: '10px',
        padding: '12px 14px',
      }}
    >
      <Flex aCenter gap={1.5}>
        <Flex
          center
          sx={{
            flexShrink: 0,
            height: 40,
            width: 40,
            borderRadius: '8px',
            backgroundColor: card?.tileBg ?? '#217346',
            color: card?.tileColor ?? '#fff',
            fontSize: 13,
            fontWeight: 700,
            textTransform: 'uppercase',
            overflow: 'hidden',
          }}
        >
          {file.is_image && file.preview ? (
            <img
              alt={file.name}
              src={file.preview}
              style={{ height: '100%', width: '100%', objectFit: 'cover' }}
            />
          ) : (
            fileSplitName(file.name).extension.slice(0, 4) || 'FILE'
          )}
        </Flex>

        <Div sx={{ flex: 1, minWidth: 0 }}>
          <Typography
            variant="body2"
            sx={{
              fontWeight: 600,
              color: card?.nameColor ?? 'text.primary',
              whiteSpace: 'nowrap',
              overflow: 'hidden',
              textOverflow: 'ellipsis',
            }}
          >
            {file.name}
          </Typography>
          <Typography
            variant="caption"
            sx={{ color: card?.metaColor ?? 'text.secondary' }}
          >
            {formatFileSize(file.size)}
          </Typography>
        </Div>

        <FiX
          size={18}
          style={{
            color: card?.removeColor ?? undefined,
            cursor: 'pointer',
            flexShrink: 0,
          }}
          onClick={onRemove}
        />
      </Flex>

      {file.progress < 100 && (
        <Flex aCenter gap={1}>
          <Div
            sx={{
              flex: 1,
              height: 6,
              borderRadius: 999,
              backgroundColor: bar?.track ?? '#dbe2ec',
              overflow: 'hidden',
            }}
          >
            <Div
              sx={{
                height: '100%',
                width: `${file.progress}%`,
                borderRadius: 999,
                backgroundColor: bar?.bar ?? '#2563eb',
                transition: 'width 120ms ease',
              }}
            />
          </Div>
          <Typography
            variant="caption"
            sx={{
              fontWeight: 600,
              color: bar?.label ?? 'text.primary',
              minWidth: 38,
              textAlign: 'right',
            }}
          >
            {file.progress} %
          </Typography>
        </Flex>
      )}
    </Flex>
  );
}
