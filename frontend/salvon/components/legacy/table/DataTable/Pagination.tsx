import TablePagination from '@mui/material/TablePagination';

import { useTranslation } from '@salvon/hooks/useTranslation';

interface Props {
  totalCount: number;
  page: number;
  isLoading: boolean;
  rowsPerPage: number;
  perPageOptions: Array<number>;
  onPageChange: (newPage: number) => void;
  onChangeRowsPerPage: (rowsPerPage: number) => void;
  enableEdgePageButtons?: boolean;
}

export default function Pagination({
  totalCount,
  page,
  isLoading,
  rowsPerPage,
  perPageOptions,
  onPageChange,
  onChangeRowsPerPage,
  enableEdgePageButtons = true,
}: Props) {
  const t = useTranslation();
  return (
    <TablePagination
      showFirstButton={enableEdgePageButtons}
      showLastButton={enableEdgePageButtons}
      labelRowsPerPage={t('pagination_per_page')}
      labelDisplayedRows={({ from, to, count }) =>
        `${from}–${to} ${t('pagination_of')} ${count}`
      }
      rowsPerPageOptions={perPageOptions}
      component="div"
      count={totalCount}
      rowsPerPage={rowsPerPage}
      page={page}
      onPageChange={(_event, newPage) => {
        if (isLoading) {
          return;
        }

        onPageChange(newPage);
      }}
      onRowsPerPageChange={(event) => {
        if (isLoading) {
          return;
        }

        onChangeRowsPerPage(+event.target.value);
      }}
    />
  );
}
