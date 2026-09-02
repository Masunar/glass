import type {
  SxProps,
  TableCellProps,
  TableContainerProps,
  TableRowProps,
  TableSortLabelProps,
} from '@mui/material';

import type { SearchProps } from './ApiTable/Search';
import type { TFunction } from 'i18next';
import type { FunctionComponent, ReactNode } from 'react';

import type { DivProps } from '@salvon/components/div';
import type { IconButtonProps } from '@salvon/components/icon-button';
import type { RowCheckerProps } from '@salvon/components/legacy/table/ApiTable/RowChecker';
import type { StaticCallable } from '@salvon/request';

export interface DataTable {
  headingColumns: Array<Column>;
  totalCount?: number;
  page?: number;
  onPageChange?: (page: number) => void;
  onChangeRowsPerPage?: (perPage: number) => void;
  order?: 'asc' | 'desc';
  orderBy?: string;
  onOrderByChange?: (orderBy: string) => void;
  isLoading?: boolean;
  children: ReactNode;
  rowsPerPage?: number;
  filters?: ReactNode;
  stickyHeader?: boolean;
  loader?:
    'none' | 'spinner' | 'linear' | 'linear_combo' | 'skeleton' | 'custom';
  enableEdgePageButtons?: boolean;
  disableOrdering?: boolean;
  disablePagination?: boolean;
  perPageOptions?: Array<number>;
  containerProps?: TableContainerProps;
  customLoader?: ReactNode;
  customDensePadding?: ReactNode;
  renderCustomLoaderOutsideBody?: boolean;
  filterContainerProps?: DivProps;
  containerSx?: SxProps;
  pageOffsetHeight?: number;
  checkboxHeaderColumn?: boolean;
  mobile?: boolean;
}

export interface CellRenderer {
  row?: any;
  index?: number;
  reloadRow: (callable?: () => void) => void;
  reloadTable: () => void;
  t: TFunction;
}

export type TableActions = Array<FunctionComponent<CellRenderer>>;

export interface Column {
  name: string;
  label: string;
  permissions?: string | Array<string>;
  ordering?: boolean;
  align?: 'left' | 'center' | 'right';
  headingCellProps?: TableCellProps;
  headingLabelProps?: TableSortLabelProps;
  rowCellProps?: TableCellProps;
  allCellProps?: TableCellProps;
  actions?: TableActions;
  render?: (props: CellRenderer) => ReactNode;
}

export interface RowRenderer {
  row: any;
  columns: Array<Column>;
  rowProps?: RendererRowProps;
  reloadTable: () => void;
  apiClass: StaticCallable;
  apiMethod: string;
  checkboxSelection?: boolean;
  rowCheckerProps?: Partial<RowCheckerProps>;
  additionalApiQueryParams?: any;
  withRowContext?: boolean;
  index?: number;
  mobile?: boolean;
}

export interface NewRowRenderer {
  rowProps?: RendererRowProps;
  reloadTable: () => void;
  index?: number;
  children?: ReactNode;
}

export interface RowRendererVariant {
  columns: Array<Column>;
  row: any;
  checkboxSelection?: boolean;
  rowCheckerDisabled?: (row: any) => boolean;
  rowCheckerProps?: Partial<RowCheckerProps>;
  renderCell: (column: Column) => ReactNode;
}

export interface CustomRowRenderer {
  row: any;
  reloadTable?: () => void;
  index: number;
  mobile?: boolean;
}

export type RendererRowProps = TableRowProps & {
  click?: (row: any) => void;
  doubleClick?: (row: any) => void;
  rowCheckerDisabled?: (row: any) => boolean;
};

export interface ApiTable {
  columns: Array<Column>;
  apiClass: StaticCallable;
  apiMethod?: string;
  dependencyObject?: string;
  filters?: ReactNode;
  rowRenderer?: FunctionComponent<CustomRowRenderer>;
  dataTableProps?: Partial<DataTable>;
  defaultPerPage?: number;
  withSearch?: boolean;
  searchProps?: SearchProps;
  withFilterClear?: boolean;
  filterClearButtonProps?: IconButtonProps;
  withTableRefresh?: boolean;
  tableRefreshButtonProps?: IconButtonProps;
  defaultOrderBy?: string;
  onFilterClear?: () => void;
  disableFilters?: boolean;
  disableLoader?: boolean;
  linearLoader?: boolean;
  perPageOptions?: Array<number>;
  filterParamDefinition?: Array<{
    name: string;
    value: any;
    defaultValue?: any;
    callable?: (value: any) => void;
  }>;
  inQuery?: boolean;
  rowProps?: RendererRowProps;
  additionalApiQueryParams?: any;
  additionalApiMethodParams?: any;
  alwaysOnRequest?: () => void;
  checkboxSelection?: boolean;
  checkboxCounter?: boolean;
  withRowContext?: boolean;
  rowCheckerProps?: Partial<RowCheckerProps>;
  massActionHeader?: ReactNode;
  onLoad?: (data: any) => void;
  preventRequest?: boolean;
}
