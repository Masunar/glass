import { Checkbox, LinearProgress } from '@mui/material';
import TableCell, { type SortDirection } from '@mui/material/TableCell';
import TableHead from '@mui/material/TableHead';
import TableSortLabel from '@mui/material/TableSortLabel';

import type { Column } from '../Interfaces';
import { useTableSelectionManipulator } from '../hooks/useTableSelectionManipulator';
import Cell from './Cell';
import LabelRenderer from './LabelRenderer';
import TRow from './TRow';
import { useRef } from 'react';

import { Div } from '@salvon/components/div';
import { sort_order } from '@salvon/consts/table';
import { usePalette } from '@salvon/hooks/useTheme';

interface Props {
  columns: Array<Column>;
  order?: 'asc' | 'desc';
  orderBy?: string;
  onOrderChange?: (orderBy: string) => void;
  isLoading: boolean;
  disableOrdering?: boolean;
  linearLoader?: boolean;
  renderLinearLoader?: boolean;
  checkboxHeaderColumn?: boolean;
  mobile?: boolean;
}

export default function THead({
  columns,
  order,
  orderBy,
  onOrderChange,
  isLoading,
  disableOrdering = false,
  linearLoader = false,
  renderLinearLoader = false,
  checkboxHeaderColumn = false,
  mobile = false,
}: Props) {
  const { anyOnPageSelected, allOnPageSelected, toggleAllOnPage } =
    useTableSelectionManipulator();
  const palette = usePalette();
  const tableHeadRef = useRef<any>(null);
  const headerHeight = tableHeadRef.current?.clientHeight ?? 55;

  const linearLoaderHeight = '3px';
  const paddings = {
    head_cell_vertical: '15px',
    cell_horizontal: '10px',
  };

  return (
    <TableHead ref={tableHeadRef}>
      {!mobile && (
        <TRow hover={false}>
          {checkboxHeaderColumn && (
            <TableCell
              padding="checkbox"
              sx={{
                border: 'none',
                ...(palette?.salvon?.table?.header ?? {}),
              }}
            >
              <Checkbox
                onChange={toggleAllOnPage}
                checked={Boolean(allOnPageSelected)}
                indeterminate={anyOnPageSelected && !allOnPageSelected}
              />
            </TableCell>
          )}
          {columns.map((column: Column) => {
            const sortDirection: SortDirection =
              orderBy === column.name ? (order ?? false) : false;

            const isColumnActive = orderBy === column.name;

            //Disable ordering when flag is disabled or any of props are false
            const orderingDisabled =
              disableOrdering ||
              !!column.actions ||
              !(column.ordering ?? true) ||
              !onOrderChange ||
              !orderBy ||
              !order;

            const allColumnSx: any = {
              ...column.allCellProps?.sx,
              ...column.headingCellProps?.sx,
              ...(linearLoader && {
                borderWidth: linearLoaderHeight,
              }),
            };

            return (
              <TableCell
                key={column.name}
                align={column.align ?? 'left'}
                sortDirection={sortDirection}
                {...column.allCellProps}
                {...column.headingCellProps}
                sx={{
                  border: 'none',
                  backdropFilter: 'blur(10px)',
                  px: paddings.cell_horizontal,
                  py: paddings.head_cell_vertical,
                  textWrap: 'nowrap',
                  fontSize: 12,
                  paddingBottom: linearLoader
                    ? `calc(${paddings.head_cell_vertical} - ${linearLoaderHeight})`
                    : paddings.head_cell_vertical,
                  ...(palette?.salvon?.table?.header ?? {}),
                  ...allColumnSx,
                }}
              >
                <TableSortLabel
                  disabled={orderingDisabled}
                  active={isColumnActive && !orderingDisabled}
                  direction={orderBy === column.name ? order : sort_order.asc}
                  hideSortIcon={orderingDisabled}
                  onClick={() => {
                    if (isLoading || orderingDisabled) {
                      return;
                    }

                    onOrderChange(column.name);
                  }}
                  {...column.headingLabelProps}
                >
                  <LabelRenderer label={column.label} />
                </TableSortLabel>
              </TableCell>
            );
          })}
        </TRow>
      )}
      {linearLoader && (
        <TRow hover={false}>
          <Cell
            colSpan={1000000}
            sx={{
              border: 'none',
              background: 'none',
              padding: 0,
              height: linearLoaderHeight,
              top: mobile
                ? 0
                : `calc(${headerHeight}px - ${linearLoaderHeight})`,
            }}
          >
            <Div className="without-transition">
              <LinearProgress
                value={100}
                variant={
                  isLoading && renderLinearLoader
                    ? 'indeterminate'
                    : 'determinate'
                }
                // @ts-ignore
                sx={{
                  height: linearLoaderHeight,
                  marginTop: `-${linearLoaderHeight}`,
                  ...(palette.salvon?.table?.linear_loader ?? {}),
                }}
              />
            </Div>
          </Cell>
        </TRow>
      )}
    </TableHead>
  );
}
