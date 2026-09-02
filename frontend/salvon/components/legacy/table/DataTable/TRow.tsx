import { type TableRowProps } from '@mui/material';
import TableRow from '@mui/material/TableRow';

export default function TRow(props: TableRowProps) {
  return (
    <TableRow hover {...props}>
      {props.children}
    </TableRow>
  );
}
