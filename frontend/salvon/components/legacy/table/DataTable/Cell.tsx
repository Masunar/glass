import type { TableCellProps } from '@mui/material';
import TableCell from '@mui/material/TableCell';

import { voc } from '@salvon/utils/object';

export type CellProps = TableCellProps & {
  collapse?: boolean;
};

export default function Cell({ children, collapse, sx, ...props }: CellProps) {
  return (
    <TableCell
      align="left"
      sx={{
        px: '10px',
        py: '5px',
        ...sx,
        ...voc(!!collapse, { border: 'none !important' }),
      }}
      {...props}
    >
      {children}
    </TableCell>
  );
}
