import { darken } from '@mui/material';
import Typography from '@mui/material/Typography';

import { type ReactNode, useState } from 'react';

import { Flex } from '@salvon/components/div';
import type { RendererRowProps } from '@salvon/components/legacy/table/Interfaces';
import { usePalette } from '@salvon/hooks/useTheme';
import type { SetState } from '@salvon/types';

import LazyLoadImage from '@app/components/image/LazyLoadImage';

interface Props {
  row: any;
  rowProps?: RendererRowProps;
  reloadTable?: () => void;
  renderAction?: (props: {
    row: any;
    reloadTable: any;
    open: boolean;
    setOpen: SetState<any>;
  }) => ReactNode;
}

export default function ImageRowRenderer({
  row,
  rowProps,
  reloadTable,
  renderAction,
}: Props) {
  const palette: any = usePalette();
  const [open, setOpen] = useState(false);

  const { click, doubleClick, ...restRowProps } = rowProps ?? {};

  const rowRenderer = (
    <Flex column>
      <Flex
        sx={{
          border: '1px solid #ccc',
          borderRadius: '5px',
          padding: '1px',
          cursor: 'pointer',
          '&:hover': { borderColor: palette.primary.contrast },
          '&:active': {
            borderColor: darken(palette.primary.contrast, 0.1),
            backgroundColor: '#fafafa',
          },
          transition: '0.1s !important',
        }}
        onClick={() => setOpen(true)}
      >
        <LazyLoadImage src={row.url} alt={row.name} />
      </Flex>
      <Flex
        sx={{ py: 1, mx: 1, overflowWrap: 'break-word' }}
        justify="space-between"
      >
        <Typography
          sx={{
            fontSize: 13,
            whiteSpace: 'wrap',
          }}
        >
          {row.media?.name?.substring(0, 22) +
            (row.media?.name?.length > 23 ? '...' : '')}
        </Typography>
      </Flex>
    </Flex>
  );

  return (
    <Flex
      onClick={() => click && click(row)}
      onDoubleClick={() => doubleClick && doubleClick(row)}
      {...restRowProps}
    >
      {renderAction && renderAction({ row, reloadTable, open, setOpen })}
      {rowRenderer}
    </Flex>
  );
}
