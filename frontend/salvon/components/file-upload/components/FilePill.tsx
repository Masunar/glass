import { Typography, alpha } from '@mui/material';

import type { StatefulFile } from '../types';
import { FiX } from 'react-icons/fi';

import { Div, Flex } from '@salvon/components/div';
import { usePalette } from '@salvon/hooks/useTheme';
import { formatFileSize } from '@salvon/utils/file';

export type FilePillProps = {
  file: StatefulFile;
  onRemove: () => void;
};

export default function FilePill({ file, onRemove }: FilePillProps) {
  const palette = usePalette();

  const uploading = file.progress < 100;
  const dotColor = uploading ? palette?.primary?.main : palette?.success?.main;
  const meta = uploading ? `${file.progress}%` : formatFileSize(file.size);

  return (
    <Flex
      aCenter
      gap={1}
      sx={{
        maxWidth: '100%',
        padding: '6px 10px 6px 12px',
        borderRadius: 999,
        backgroundColor: alpha(dotColor ?? '#94a3b8', 0.08),
      }}
    >
      <Div
        sx={{
          flexShrink: 0,
          height: 8,
          width: 8,
          borderRadius: '50%',
          backgroundColor: dotColor,
        }}
      />
      <Typography
        variant="body2"
        sx={{
          fontWeight: 600,
          color: 'text.primary',
          whiteSpace: 'nowrap',
          overflow: 'hidden',
          textOverflow: 'ellipsis',
        }}
      >
        {file.name}
      </Typography>
      <Typography
        variant="caption"
        sx={{
          flexShrink: 0,
          color: 'text.secondary',
          fontWeight: uploading ? 600 : 400,
        }}
      >
        {meta}
      </Typography>
      <FiX
        size={15}
        style={{ flexShrink: 0, cursor: 'pointer', color: '#94a3b8' }}
        onClick={onRemove}
      />
    </Flex>
  );
}
