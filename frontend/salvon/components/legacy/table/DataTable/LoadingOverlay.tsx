import { Skeleton, TableBody } from '@mui/material';
import Table from '@mui/material/Table';
import TableRow from '@mui/material/TableRow';

import Cell from './Cell';
import TRow from './TRow';

interface Props {
  columnCount: number;
  rowCount: number;
  mobile?: boolean;
}

export default function LoadingOverlay({
  columnCount,
  rowCount,
  mobile,
}: Props) {
  if (mobile) {
    return (
      <TableBody>
        {[...Array(rowCount)].map((_row, rowIterator) => {
          return (
            <TableRow key={rowIterator} sx={{ minHeight: '75px' }}>
              <Cell>
                <Table>
                  <TableBody>
                    {[...Array(2)].map((_cell, cellIterator, array) => (
                      <TRow hover={false} key={cellIterator}>
                        <Cell
                          sx={{
                            borderBottom:
                              columnCount <= 2 &&
                              cellIterator === array.length - 1
                                ? ''
                                : 'none',
                          }}
                        >
                          <Skeleton />
                        </Cell>
                        <Cell
                          sx={{
                            borderBottom:
                              columnCount <= 2 &&
                              cellIterator === array.length - 1
                                ? ''
                                : 'none',
                          }}
                        >
                          <Skeleton />
                        </Cell>
                      </TRow>
                    ))}
                    {[...Array(columnCount > 2 ? columnCount - 2 : 0)].map(
                      (_cell, cellIterator, array) => {
                        return (
                          <TRow hover={false} key={cellIterator}>
                            <Cell
                              sx={{
                                borderBottom:
                                  cellIterator === array.length - 1
                                    ? 'none'
                                    : '',
                              }}
                            >
                              <Skeleton />
                            </Cell>
                            <Cell
                              sx={{
                                borderBottom:
                                  cellIterator === array.length - 1
                                    ? 'none'
                                    : '',
                              }}
                            >
                              <Skeleton />
                            </Cell>
                          </TRow>
                        );
                      },
                    )}
                  </TableBody>
                </Table>
              </Cell>
            </TableRow>
          );
        })}
      </TableBody>
    );
  }

  return (
    <TableBody>
      {[...Array(rowCount)].map((_row, rowIterator) => {
        return (
          <TableRow key={rowIterator} sx={{ minHeight: '75px' }}>
            <Cell>
              <Skeleton variant="rectangular" width={40} height={18} />
            </Cell>
            <Cell>
              <Skeleton />
            </Cell>
            {[...Array(columnCount > 2 ? columnCount - 2 : 0)].map(
              (_cell, cellIterator) => {
                return (
                  <Cell key={cellIterator}>
                    <Skeleton />
                  </Cell>
                );
              },
            )}
          </TableRow>
        );
      })}
    </TableBody>
  );
}
