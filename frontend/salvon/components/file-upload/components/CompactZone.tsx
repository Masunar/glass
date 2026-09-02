import { Typography } from '@mui/material';

import type { ReactNode } from 'react';
import { FiUpload } from 'react-icons/fi';

import { Div, Flex } from '@salvon/components/div';
import { usePalette } from '@salvon/hooks/useTheme';

export type CompactZoneProps = {
  title: ReactNode;
  hint?: string;
  children?: ReactNode;
};

export default function CompactZone({
  title,
  hint,
  children,
}: CompactZoneProps) {
  const dz = usePalette()?.salvon?.file_upload?.dropzone;

  return (
    <Flex aCenter gap={1.5} sx={{ width: '100%' }}>
      <Flex
        center
        sx={{
          flexShrink: 0,
          height: 44,
          width: 44,
          borderRadius: '10px',
          backgroundColor: dz?.badgeBg ?? 'primary.main',
          color: dz?.badgeColor ?? '#fff',
        }}
      >
        <FiUpload size={18} />
      </Flex>

      <Div sx={{ flex: 1, minWidth: 0, textAlign: 'left' }}>
        <Typography
          variant="body2"
          sx={{ fontWeight: 600, color: 'text.primary', userSelect: 'none' }}
        >
          {title}
        </Typography>
        {hint && (
          <Typography
            variant="caption"
            sx={{ color: dz?.hintColor ?? 'text.disabled' }}
          >
            {hint}
          </Typography>
        )}
      </Div>

      {children && <Div sx={{ flexShrink: 0 }}>{children}</Div>}
    </Flex>
  );
}
