import Table from '@mui/material/Table';
import TableBody from '@mui/material/TableBody';

import Cell from '../../../DataTable/Cell';
import TRow from '../../../DataTable/TRow';
import type { RowRendererVariant } from '../../../Interfaces';
import RowChecker from '../../RowChecker';

import { useTranslation } from '@salvon/hooks/useTranslation';

export default function Mobile({
  checkboxSelection,
  columns,
  row,
  rowCheckerProps,
  rowCheckerDisabled,
  renderCell,
}: RowRendererVariant) {
  const t = useTranslation();

  return (
    <Cell>
      <Table>
        <TableBody>
          {checkboxSelection && (
            <TRow hover={false}>
              <Cell
                sx={{
                  fontWeight: 'bold',
                  borderBottom: columns?.length > 0 ? '' : 'none',
                }}
              >
                {t('selection')}
              </Cell>
              <Cell
                sx={{
                  paddingLeft: '4px',
                  borderBottom: columns?.length > 0 ? '' : 'none',
                }}
              >
                <RowChecker
                  row={row}
                  //@ts-ignore
                  componentProps={rowCheckerProps}
                  disabled={rowCheckerDisabled && rowCheckerDisabled(row)}
                />
              </Cell>
            </TRow>
          )}
          {columns.map((column, index, array) => {
            return (
              <TRow key={column.name} hover={false}>
                <Cell
                  sx={{
                    fontWeight: 'bold',
                    borderBottom: index === array.length - 1 ? 'none' : '',
                    textWrap: 'wrap',
                    width: '50%',
                  }}
                >
                  {t(column.label)}
                </Cell>
                <Cell
                  sx={{
                    borderBottom: index === array.length - 1 ? 'none' : '',
                  }}
                  align={column.align ?? 'left'}
                  {...column.allCellProps}
                  {...column.rowCellProps}
                >
                  {renderCell(column)}
                </Cell>
              </TRow>
            );
          })}
        </TableBody>
      </Table>
    </Cell>
  );
}
