import Table from '@mui/material/Table';
import TableBody from '@mui/material/TableBody';
import TableContainer from '@mui/material/TableContainer';

import CenterMessage from './DataTable/CenterMessage';
import LoadingOverlay from './DataTable/LoadingOverlay';
import Pagination from './DataTable/Pagination';
import THead from './DataTable/THead';
import type { DataTable as Props } from './Interfaces';
import Loading from './Loading';

import { Div, Flex } from '@salvon/components/div';
import { useTranslation } from '@salvon/hooks/useTranslation';

export default function DataTable({
  totalCount,
  headingColumns,
  page,
  onPageChange,
  onChangeRowsPerPage,
  order,
  orderBy,
  onOrderByChange,
  filters,
  filterContainerProps,
  children,
  containerSx,
  containerProps,
  customLoader,
  customDensePadding,
  pageOffsetHeight = 350,
  stickyHeader = true,
  loader = 'none',
  isLoading = false,
  enableEdgePageButtons = true,
  disableOrdering = false,
  checkboxHeaderColumn = false,
  disablePagination = false,
  renderCustomLoaderOutsideBody = false,
  rowsPerPage = 20,
  perPageOptions = [5, 10, 20, 50],
  mobile = false,
}: Props) {
  const t = useTranslation();

  const renderLinear = loader === 'linear' || loader === 'linear_combo';

  const renderSkeleton =
    (loader === 'skeleton' && isLoading) ||
    (loader === 'linear_combo' && isLoading && totalCount === 0);

  const renderSpinner = loader === 'spinner' && isLoading;
  const renderCustom = loader === 'custom' && isLoading;
  const renderNone = loader === 'none';

  const renderNoResultsMessage =
    !isLoading && totalCount !== undefined && !totalCount;

  return (
    <Div sx={{ width: '100%' }}>
      {!!filters && (
        <Div
          sx={{ paddingTop: 1, marginBottom: '4px' }}
          {...filterContainerProps}
        >
          {filters}
        </Div>
      )}
      <TableContainer
        {...containerProps}
        sx={{
          ...(stickyHeader &&
            totalCount && {
              minHeight: '250px',
              maxHeight: `calc(100dvh - ${pageOffsetHeight}px)`,
            }),
          ...containerSx,
        }}
      >
        <Table
          stickyHeader={stickyHeader}
          sx={{ minWidth: mobile ? 100 : 450, overflow: 'auto' }}
        >
          <THead
            order={order}
            orderBy={orderBy}
            columns={headingColumns}
            onOrderChange={onOrderByChange}
            isLoading={isLoading}
            disableOrdering={disableOrdering}
            linearLoader={renderLinear}
            renderLinearLoader={!renderSkeleton}
            checkboxHeaderColumn={checkboxHeaderColumn}
            mobile={mobile}
          />

          {(!isLoading || (renderLinear && !renderSkeleton) || renderNone) && (
            <TableBody>{children}</TableBody>
          )}

          {renderSkeleton && (
            <LoadingOverlay
              columnCount={headingColumns.length}
              rowCount={rowsPerPage}
              mobile={mobile}
            />
          )}

          {renderCustom && !renderCustomLoaderOutsideBody && customLoader}
        </Table>
        {renderNoResultsMessage && <CenterMessage text={t('no_results')} />}
      </TableContainer>

      {renderSpinner && (
        <Loading sx={{ marginTop: '60px', marginBottom: '40px' }} />
      )}

      {renderCustom && renderCustomLoaderOutsideBody && customLoader}

      <Flex jBetween>
        <Flex center>{customDensePadding && customDensePadding}</Flex>
        {!disablePagination &&
          page !== undefined &&
          totalCount !== undefined &&
          onPageChange &&
          onChangeRowsPerPage && (
            <Pagination
              page={page}
              totalCount={totalCount}
              isLoading={isLoading}
              rowsPerPage={rowsPerPage}
              perPageOptions={perPageOptions}
              onPageChange={onPageChange}
              onChangeRowsPerPage={onChangeRowsPerPage}
              enableEdgePageButtons={enableEdgePageButtons}
            />
          )}
      </Flex>
    </Div>
  );
}
