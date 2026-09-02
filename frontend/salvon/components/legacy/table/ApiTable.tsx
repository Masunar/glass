import CheckedCount from './ApiTable/CheckedCount';
import RowRenderer from './ApiTable/RowRenderer/RowRenderer';
import Search, { type SearchRef } from './ApiTable/Search';
import DataTable from './DataTable';
import { type ApiTable as Props } from './Interfaces';
import TableSelectionProvider from './provider/TableSelectionProvider';
import {
  type ForwardedRef,
  createElement,
  forwardRef,
  useEffect,
  useImperativeHandle,
  useRef,
  useState,
} from 'react';
import { AiOutlineClear } from 'react-icons/ai';
import { TbRefresh } from 'react-icons/tb';

// import { hasPermissionTo } from '@salvon/auth/accessChecker';
import { Flex } from '@salvon/components/div';
import { BaseIconButton } from '@salvon/components/icon-button';
import { type OrderNames, sort_order } from '@salvon/consts/table';
import { useIsUnder } from '@salvon/hooks/useMediaQuery';
import { useRenderTrigger } from '@salvon/hooks/useRenderTrigger';
import { usePalette } from '@salvon/hooks/useTheme';
import { useTranslation } from '@salvon/hooks/useTranslation';
import type { ResponseProps } from '@salvon/request';
import { isAccessDenied } from '@salvon/utils/api';
import { notifyError } from '@salvon/utils/notify';
import { readQueryParam, setSearchParam } from '@salvon/utils/query';
import { isUndefined } from '@salvon/utils/type-check';

type QueryParamScheme = {
  [key: string]: any;
};

export type ApiTableRef = {
  resetPage: () => void;
  reloadTable: () => void;
  setSearchValue: (v: any) => void;
  updateSearchValue: (v: any) => void;
  checkboxes: {
    currentlySelected: Array<any>;
    clearSelected: () => void;
  };
};

export const parseDependency = (content: any, dependencyObject: string) => {
  const keys = dependencyObject === '' ? [] : dependencyObject.split('.');

  let value = content.data;
  keys.map((key) => {
    value = value?.[key];
  });

  return value || [];
};

function ApiTable(
  {
    filters,
    columns,
    apiClass,
    rowRenderer,
    defaultOrderBy,
    dataTableProps,
    disableFilters = false,
    withSearch = true,
    withFilterClear = true,
    withTableRefresh = false,
    searchProps,
    filterClearButtonProps,
    additionalApiQueryParams = {},
    additionalApiMethodParams = {},
    alwaysOnRequest = () => {},
    tableRefreshButtonProps,
    rowProps,
    filterParamDefinition = [],
    apiMethod = 'list',
    dependencyObject = 'items',
    defaultPerPage = 20,
    perPageOptions = [20, 50, 100, 200],
    onFilterClear,
    massActionHeader,
    inQuery = true,
    checkboxSelection = false,
    checkboxCounter = true,
    withRowContext = false,
    rowCheckerProps = {},
    onLoad,
    preventRequest = false,
  }: Props,
  ref: ForwardedRef<ApiTableRef>,
) {
  const tableDefaultOrderBy = defaultOrderBy ?? columns[0].name;
  const queryParamPage = +readQueryParam('page', '1') - 1 || 0;
  const palette = usePalette();
  const mobile = useIsUnder('sm');

  const [currentlySelected, setCurrentlySelected] = useState<Array<any>>([]);
  const [loading, setLoading] = useState(true);
  const [data, setData] = useState<Array<any>>([]);
  const [totalCount, setTotalCount] = useState<number>(0);
  const [page, setPage] = useState<number>(queryParamPage);
  const [rowsPerPage, setRowsPerPage] = useState<number>(defaultPerPage);

  const [order, setOrder] = useState<OrderNames>(
    // @ts-ignore
    readQueryParam('order', sort_order.desc),
  );
  const [orderBy, setOrderBy] = useState(
    readQueryParam('order_by', tableDefaultOrderBy),
  );
  const [searchQuery, setSearchQuery] = useState(
    readQueryParam('search_query'),
  );
  const [reloadTable, triggerTableReload] = useRenderTrigger();
  const t = useTranslation();
  const searchQueryRef = useRef<SearchRef | null>(null);
  const useEffectVariables =
    filterParamDefinition.map((param) => param.value) ?? [];

  const filterQueryParamsDefinition: QueryParamScheme = {};

  const pageExceeded =
    rowsPerPage > 0 && page + 1 > Math.ceil(totalCount / rowsPerPage);

  filterParamDefinition.forEach((param) => {
    filterQueryParamsDefinition[param.name] = param.value;
  });

  const [idFilter, setIdFilter] = useState(+readQueryParam('id'));

  useEffect(() => {
    if (totalCount > 0 && pageExceeded) {
      setPage(0);
    }
  }, [totalCount]);

  const handleOuterFiltersClean = () => {
    filterParamDefinition.forEach((param) => {
      if (!param.callable) {
        return;
      }

      if (isUndefined(param.defaultValue)) {
        throw new Error(
          'Using callable for automatic filter cleaning requires defaultValue parameter.',
        );
      }

      param.callable(param.defaultValue);
    });
  };

  useEffect(() => {
    if (!inQuery) {
      return;
    }

    const currentUrl = new URL(window.location.href);
    const searchParams = new URLSearchParams(currentUrl.search);

    Object.keys(filterQueryParamsDefinition).map((key: string) => {
      setSearchParam(searchParams, key, filterQueryParamsDefinition[key]);
    });

    currentUrl.search = searchParams.toString();
    window.history.replaceState({}, '', currentUrl.toString());
  }, [...useEffectVariables]);

  useEffect(() => {
    if (!inQuery) {
      return;
    }
    searchQueryRef.current?.setDefaultValue(readQueryParam('search_query', ''));
  }, []);

  const fetchItems = async () => {
    setLoading(true);

    if (preventRequest) {
      return;
    }

    const data = {
      limit: rowsPerPage,
      page: page,
      order_by: orderBy,
      order: order,
      search_query: searchQuery,
      ...filterQueryParamsDefinition,
    };

    if (idFilter > 0) {
      // @ts-ignore
      data.ids = idFilter;
    }

    const res = await apiClass[apiMethod](
      { ...data, ...additionalApiQueryParams },
      additionalApiMethodParams,
    );

    alwaysOnRequest();
    handleResponse(res);

    if (inQuery) {
      handleUpdateQuery();
    }
  };

  useEffect(() => {
    fetchItems();
  }, [
    reloadTable,
    searchQuery,
    rowsPerPage,
    page,
    orderBy,
    order,
    preventRequest,
    idFilter,
    ...useEffectVariables,
  ]);

  const handleResponse = ({ content, response }: ResponseProps<any>) => {
    setLoading(false);
    if (isAccessDenied(response)) {
      return;
    }

    if (!response.success) {
      notifyError(t('api.ise'));
      return;
    }

    const dependency = parseDependency(content, dependencyObject);
    if (onLoad) {
      onLoad(dependency);
    }

    setData(dependency);
    setTotalCount(content.data.count || dependency?.length);
  };

  const onChangeRowsPerPage = (rowsPerPage: number) => {
    setRowsPerPage(rowsPerPage);
    setPage(0);
  };

  const onOrderByChange = (newOrderBy: string) => {
    if (totalCount < 2) {
      return;
    }

    if (newOrderBy === orderBy) {
      setOrder(order === sort_order.asc ? sort_order.desc : sort_order.asc);
      return;
    }

    setOrder(sort_order.asc);
    setOrderBy(newOrderBy);
  };

  const resetFilters = () => {
    setPage(0);
    setRowsPerPage(defaultPerPage);
    setOrderBy(tableDefaultOrderBy);
    setOrder(sort_order.desc);

    setSearchQuery('');
    searchQueryRef.current?.clearSearch();
    setIdFilter(0);

    if (!onFilterClear) {
      handleOuterFiltersClean();
      return;
    }

    onFilterClear();
  };

  const handleUpdateQuery = () => {
    const queryObject: { [key: string]: string } = {
      page: String(page + 1),
      order_by: orderBy,
      order: order,
      search_query: searchQuery,
      ...filterQueryParamsDefinition,
    };

    const currentUrl = new URL(window.location.href);
    const searchParams = new URLSearchParams(currentUrl.search);

    Object.keys(queryObject).map((key) => {
      setSearchParam(searchParams, key, String(queryObject[key]));
    });

    currentUrl.search = searchParams.toString();
    window.history.replaceState({}, '', currentUrl.toString());
  };

  const dataTableFilters = (
    <>
      <Flex jBetween wrap>
        <Flex gap={1} wrap sx={{ flex: 1 }}>
          {withSearch && (
            <Search
              ref={searchQueryRef}
              onSearch={(value: string) => {
                setSearchQuery(value);
                setPage(0);
              }}
              {...searchProps}
            />
          )}
          {filters}
        </Flex>
        <Flex gap={1}>
          {checkboxSelection && checkboxCounter && <CheckedCount />}
          {withFilterClear && (
            <BaseIconButton
              onClick={resetFilters}
              color={'primary'}
              icon={<AiOutlineClear />}
              label={t('clear_filters')}
              {...filterClearButtonProps}
            />
          )}
          {withTableRefresh && (
            <BaseIconButton
              onClick={triggerTableReload}
              color={'primary'}
              icon={<TbRefresh />}
              label={t('refresh')}
              {...tableRefreshButtonProps}
            />
          )}
        </Flex>
      </Flex>
      {massActionHeader && <Flex mt={2}>{massActionHeader}</Flex>}
    </>
  );

  //Filter permission before passing through components
  // columns = columns.filter((column) => hasPermissionTo(column.permissions));

  const handleResetPage = () => {
    setPage(0);
  };

  useImperativeHandle(ref, () => ({
    resetPage: handleResetPage,
    reloadTable: triggerTableReload,
    checkboxes: {
      currentlySelected,
      clearSelected: () => setCurrentlySelected([]),
    },
    setSearchValue: (v: string) => {
      searchQueryRef.current?.setValue(v);
    },
    updateSearchValue: (v: string) => {
      searchQueryRef.current?.setValue(v);
      setSearchQuery(v);
    },
  }));

  return (
    <TableSelectionProvider
      data={data}
      reloadTable={triggerTableReload}
      resetPage={handleResetPage}
      currentlySelected={currentlySelected}
      setCurrentlySelected={setCurrentlySelected}
    >
      <DataTable
        headingColumns={columns}
        isLoading={loading}
        totalCount={totalCount}
        page={pageExceeded ? 0 : page}
        onPageChange={(page: number) => setPage(page)}
        onChangeRowsPerPage={onChangeRowsPerPage}
        onOrderByChange={onOrderByChange}
        order={order}
        orderBy={orderBy}
        rowsPerPage={rowsPerPage}
        filters={!disableFilters && dataTableFilters}
        loader="linear_combo"
        perPageOptions={perPageOptions}
        checkboxHeaderColumn={checkboxSelection}
        mobile={mobile}
        {...dataTableProps}
      >
        {data?.map((row: any, iterator: number) => {
          const reactKey = row.id ?? iterator;

          if (rowRenderer) {
            return createElement(rowRenderer, {
              key: reactKey,
              row: row,
              reloadTable: triggerTableReload,
              index: iterator,
              mobile: mobile,
            });
          }

          return (
            <RowRenderer
              apiClass={apiClass}
              apiMethod={apiMethod}
              key={reactKey}
              columns={columns}
              row={row}
              reloadTable={triggerTableReload}
              rowProps={{
                ...rowProps,
                sx: {
                  ...((palette.salvon?.table?.row ?? {}) as any),
                  ...(rowProps?.sx && rowProps.sx),
                  ...(iterator % 2 === 0 && {
                    ...((palette.salvon?.table?.row_even ?? {}) as any),
                  }),
                },
              }}
              checkboxSelection={checkboxSelection}
              rowCheckerProps={rowCheckerProps}
              withRowContext={withRowContext}
              index={iterator}
              mobile={mobile}
            />
          );
        })}
      </DataTable>
    </TableSelectionProvider>
  );
}

const ForwardedApiTable = forwardRef(ApiTable);

export default ForwardedApiTable;
