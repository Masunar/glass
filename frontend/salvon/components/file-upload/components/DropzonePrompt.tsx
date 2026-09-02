import { Typography } from '@mui/material';

import type { ReactNode } from 'react';
import { FiSlash, FiUploadCloud } from 'react-icons/fi';

import { Div, Flex } from '@salvon/components/div';
import { usePalette } from '@salvon/hooks/useTheme';

export type DropzonePromptProps = {
  prompt: ReactNode;
  browse: string;
  reject: ReactNode;
  tooLarge: ReactNode;
  isReject?: boolean;
  isTooLarge?: boolean;
  children?: ReactNode;
};

export default function DropzonePrompt({
  prompt,
  browse,
  reject,
  tooLarge,
  isReject = false,
  isTooLarge = false,
  children,
}: DropzonePromptProps) {
  const dz = usePalette()?.salvon?.file_upload?.dropzone;
  const alert = isReject || isTooLarge;

  return (
    <Flex center column gap={1.5}>
      <Div sx={{ position: 'relative', height: 56, width: 56 }}>
        <Div
          sx={{
            position: 'absolute',
            top: 0,
            left: 2,
            height: 44,
            width: 40,
            borderRadius: '8px',
            backgroundColor: 'action.hover',
          }}
        />
        <Flex
          center
          sx={{
            position: 'absolute',
            bottom: 0,
            right: 0,
            height: 34,
            width: 34,
            borderRadius: '50%',
            backgroundColor: dz?.badgeBg ?? 'primary.main',
            color: dz?.badgeColor ?? '#fff',
          }}
        >
          {alert ? <FiSlash size={17} /> : <FiUploadCloud size={17} />}
        </Flex>
      </Div>

      {alert ? (
        <Typography
          variant="body2"
          sx={{ userSelect: 'none', color: 'text.secondary' }}
        >
          {isReject ? reject : tooLarge}
        </Typography>
      ) : (
        <Typography
          variant="body2"
          sx={{ userSelect: 'none', color: 'text.secondary' }}
        >
          {prompt}
          <Typography
            component="span"
            variant="body2"
            sx={{
              fontWeight: 600,
              textDecoration: 'underline',
              color: dz?.linkColor ?? 'text.primary',
            }}
          >
            {browse}
          </Typography>
        </Typography>
      )}

      {children}
    </Flex>
  );
}
