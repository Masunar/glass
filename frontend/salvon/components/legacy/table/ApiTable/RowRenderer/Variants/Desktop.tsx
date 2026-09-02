import Cell from '../../../DataTable/Cell';
import type { RowRendererVariant } from '../../../Interfaces';
import RowChecker from '../../RowChecker';

export default function Desktop({
  checkboxSelection,
  columns,
  row,
  rowCheckerProps,
  rowCheckerDisabled,
  renderCell,
}: RowRendererVariant) {
  return (
    <>
      {checkboxSelection && (
        <Cell sx={{ paddingLeft: '4px' }}>
          <RowChecker
            row={row}
            componentProps={rowCheckerProps}
            disabled={rowCheckerDisabled && rowCheckerDisabled(row)}
          />
        </Cell>
      )}
      {columns.map((column) => {
        return (
          <Cell
            key={column.name}
            align={column.align ?? 'left'}
            sx={{ fontSize: 12 }}
            {...column.allCellProps}
            {...column.rowCellProps}
          >
            {renderCell(column)}
          </Cell>
        );
      })}
    </>
  );
}
