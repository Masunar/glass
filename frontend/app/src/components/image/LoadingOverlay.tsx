import { CircularProgress, darken } from '@mui/material';

import { Flex } from '@salvon/components/div';
import { usePalette } from '@salvon/hooks/useTheme';

interface Props {
  rowCount: number;
}

export default function LoadingOverlay({ rowCount }: Props) {
  const palette = usePalette();
  return (
    <Flex gap={1} wrap>
      {[...Array(rowCount)].map((_row, rowIterator) => {
        return (
          <Flex
            sx={{
              border: '1px solid #ccc',
              borderRadius: '5px',
              padding: '1px',
              cursor: 'pointer',
              //@ts-ignore
              '&:hover': { borderColor: palette.primary.contrast },
              '&:active': {
                //@ts-ignore
                borderColor: darken(palette.primary.contrast, 0.1),
                backgroundColor: '#fafafa',
              },
              transition: '0.1s !important',
              width: '200px',
              height: '160px',
            }}
            center
          >
            <CircularProgress size={30} />
          </Flex>
        );
      })}
    </Flex>
  );
}
