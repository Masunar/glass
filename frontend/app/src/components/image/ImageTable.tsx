import { type TableContainerProps } from '@mui/material';

import LoadingOverlay from './LoadingOverlay';
import { type ReactNode } from 'react';

import { Div, type DivProps, Flex } from '@salvon/components/div';
import CenterMessage from '@salvon/components/legacy/table/DataTable/CenterMessage';
import Pagination from '@salvon/components/legacy/table/DataTable/Pagination';
import Loading from '@salvon/components/legacy/table/Loading';
import { useTranslation } from '@salvon/hooks/useTranslation';

export interface ImageTableProps {
  totalCount?: number;
  page?: number;
  onPageChange?: (page: number) => void;
  onChangeRowsPerPage?: (perPage: number) => void;
  isLoading?: boolean;
  children: ReactNode;
  rowsPerPage?: number;
  filters?: ReactNode;
  loader?:
    | 'none'
    | 'spinner'
    | 'linear'
    | 'linear_combo'
    | 'skeleton'
    | 'spinner_combo'
    | 'custom';
  enableEdgePageButtons?: boolean;
  disablePagination?: boolean;
  perPageOptions?: Array<number>;
  containerProps?: TableContainerProps;
  customLoader?: ReactNode;
  renderCustomLoaderOutsideBody?: boolean;
  filterContainerProps?: DivProps;
  pageOffsetHeight?: number;
  checkboxHeaderColumn?: boolean;
  massActionHeader?: ReactNode;
}

export default function ImageTable({
  totalCount,
  page,
  onPageChange,
  onChangeRowsPerPage,
  filters,
  filterContainerProps,
  children,
  containerProps,
  customLoader,
  pageOffsetHeight = 350,
  loader = 'none',
  isLoading = false,
  enableEdgePageButtons = true,
  disablePagination = false,
  renderCustomLoaderOutsideBody = false,
  rowsPerPage = 20,
  perPageOptions = [20, 50, 100, 200],
}: ImageTableProps) {
  const t = useTranslation();

  const renderLinear = loader === 'linear' || loader === 'linear_combo';

  const renderSkeleton =
    (loader === 'skeleton' && isLoading) ||
    (loader === 'linear_combo' && isLoading && totalCount === 0);

  const renderSpinner =
    (loader === 'spinner' && isLoading) ||
    (loader === 'spinner_combo' && isLoading && totalCount === 0);
  const renderCustom = loader === 'custom' && isLoading;
  const renderNone = loader === 'none';

  const renderNoResultsMessage =
    !isLoading && totalCount !== undefined && !totalCount;

  return (
    <Div
      sx={{
        width: '100%',
      }}
    >
      {!!filters && (
        <Div sx={{ paddingTop: 1, mb: 3 }} {...filterContainerProps}>
          {filters}
        </Div>
      )}
      <Flex {...containerProps}>
        {(!isLoading || (renderLinear && !renderSkeleton) || renderNone) && (
          <Flex
            wrap
            sx={{
              gap: 2,
              overflow: 'auto',
              maxHeight: `calc(100dvh - ${pageOffsetHeight}px)`,
            }}
          >
            {children}
          </Flex>
        )}
      </Flex>

      {renderSkeleton && <LoadingOverlay rowCount={rowsPerPage} />}

      {renderSpinner && (
        <Loading sx={{ marginTop: '60px', marginBottom: '40px' }} />
      )}

      {renderCustom && renderCustomLoaderOutsideBody && customLoader}

      {renderNoResultsMessage && <CenterMessage text={t('no_results')} />}

      <Flex justify="end">
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
