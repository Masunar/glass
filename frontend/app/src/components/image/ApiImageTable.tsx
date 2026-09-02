import ImageRowRenderer from './ImageRowRenderer';
import ImageTable, { type ImageTableProps } from './ImageTable';
import {
  type ForwardedRef,
  type FunctionComponent,
  type ReactNode,
  forwardRef,
  useEffect,
  useImperativeHandle,
  useRef,
  useState,
} from 'react';
import { AiOutlineClear } from 'react-icons/ai';
import { TbRefresh } from 'react-icons/tb';

import { Flex } from '@salvon/components/div';
import {
  IconButton,
  type IconButtonProps,
} from '@salvon/components/icon-button';
import { parseDependency } from '@salvon/components/legacy/table/ApiTable';
import Search, {
  type SearchProps,
  type SearchRef,
} from '@salvon/components/legacy/table/ApiTable/Search';
import {
  type CustomRowRenderer,
  type RendererRowProps,
} from '@salvon/components/legacy/table/Interfaces';
import { sort_order } from '@salvon/consts/table';
import { useRenderTrigger } from '@salvon/hooks/useRenderTrigger';
import { useTranslation } from '@salvon/hooks/useTranslation';
import type { ResponseProps, StaticCallable } from '@salvon/request';
import type { SetState } from '@salvon/types';
import { isAccessDenied } from '@salvon/utils/api';
import { notifyError } from '@salvon/utils/notify';
import { readQueryParam, setQueryParam } from '@salvon/utils/query';
import { isUndefined } from '@salvon/utils/type-check';

type QueryParamScheme = {
  [key: string]: any;
};

export type ImageTableRef = {
  resetPage: () => void;
  reloadTable: () => void;
};

interface Props {
  name?: string;
  apiClass: StaticCallable;
  apiMethod?: string;
  filters?: ReactNode;
  rowRenderer?: FunctionComponent<CustomRowRenderer>;
  dataTableProps?: Partial<ImageTableProps>;
  defaultPerPage?: number;
  withSearch?: boolean;
  searchProps?: SearchProps;
  withFilterClear?: boolean;
  filterClearButtonProps?: IconButtonProps;
  withTableRefresh?: boolean;
  tableRefreshButtonProps?: IconButtonProps;
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
  withRowContext?: boolean;
  massActionHeader?: ReactNode;
  dependencyObject?: string;
  onLoad?: (data: any) => void;
  renderAction?: (props: {
    row: any;
    reloadTable: any;
    open: boolean;
    setOpen: SetState<any>;
  }) => ReactNode;
  staticOption?: ({ reloadTable }: { reloadTable: () => void }) => ReactNode;
}

function ApiImageTable(
  {
    filters,
    apiClass,
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
    defaultPerPage = 20,
    perPageOptions = [20, 50, 100, 200],
    onFilterClear,
    massActionHeader,
    inQuery = true,
    dependencyObject,
    onLoad,
    renderAction,
    staticOption,
  }: Props,
  ref: ForwardedRef<ImageTableRef>,
) {
  const queryParamPage = +readQueryParam('page', '1') - 1 || 0;

  const [loading, setLoading] = useState(true);
  const [data, setData] = useState<Array<any>>([]);
  const [totalCount, setTotalCount] = useState<number>(0);
  const [page, setPage] = useState<number>(queryParamPage);
  const [rowsPerPage, setRowsPerPage] = useState<number>(defaultPerPage);

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

    Object.keys(filterQueryParamsDefinition).map((key: string) => {
      setQueryParam(key, filterQueryParamsDefinition[key]);
    });
  }, [...useEffectVariables]);

  useEffect(() => {
    if (!inQuery) {
      return;
    }
    searchQueryRef.current?.setDefaultValue(readQueryParam('search_query', ''));
  }, []);

  const fetchItems = async () => {
    setLoading(true);
    const data = {
      limit: rowsPerPage,
      page: page,
      search_query: searchQuery,
      order: sort_order.desc,
      order_by: 'id',
      ...filterQueryParamsDefinition,
    };

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
  }, [reloadTable, searchQuery, rowsPerPage, page, ...useEffectVariables]);

  const handleResponse = ({ content, response }: ResponseProps<any>) => {
    setLoading(false);

    if (isAccessDenied(response)) {
      return;
    }

    if (!response.success) {
      notifyError(t('api.ise'));
      return;
    }

    const dependency = parseDependency(
      content,
      !dependencyObject && dependencyObject !== '' ? 'items' : dependencyObject,
    );

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

  const resetFilters = () => {
    setPage(0);
    setRowsPerPage(defaultPerPage);

    setSearchQuery('');
    searchQueryRef.current?.clearSearch();

    if (!onFilterClear) {
      handleOuterFiltersClean();
      return;
    }

    onFilterClear();
  };

  const handleUpdateQuery = () => {
    const queryObject: { [key: string]: string } = {
      page: String(page + 1),
      search_query: searchQuery,
      ...filterQueryParamsDefinition,
    };

    Object.keys(queryObject).map((key) => {
      setQueryParam(key, String(queryObject[key]));
    });
  };

  const dataTableFilters = (
    <>
      <Flex justify="space-between" wrap>
        <Flex gap={1} wrap>
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
          {withFilterClear && (
            <IconButton
              onClick={resetFilters}
              color={'primary'}
              icon={<AiOutlineClear size={21} />}
              label={t('clear_filters')}
              {...filterClearButtonProps}
            />
          )}
          {withTableRefresh && (
            <IconButton
              onClick={triggerTableReload}
              color={'primary'}
              icon={<TbRefresh size={21} />}
              label={t('refresh')}
              {...tableRefreshButtonProps}
            />
          )}
        </Flex>
      </Flex>
      {massActionHeader && <Flex mt={2}>{massActionHeader}</Flex>}
    </>
  );

  const handleResetPage = () => {
    setPage(0);
  };

  useImperativeHandle(ref, () => ({
    resetPage: handleResetPage,
    reloadTable: triggerTableReload,
  }));

  return (
    <ImageTable
      isLoading={loading}
      totalCount={totalCount}
      page={pageExceeded ? 0 : page}
      onPageChange={(page: number) => setPage(page)}
      onChangeRowsPerPage={onChangeRowsPerPage}
      rowsPerPage={rowsPerPage}
      filters={!disableFilters && dataTableFilters}
      loader="spinner"
      perPageOptions={perPageOptions}
      {...dataTableProps}
    >
      {staticOption && staticOption({ reloadTable: triggerTableReload })}
      {data?.map((row: any, iterator: number) => {
        const reactKey = row.id ?? iterator;

        return (
          <ImageRowRenderer
            key={reactKey}
            row={row}
            rowProps={rowProps}
            reloadTable={triggerTableReload}
            renderAction={renderAction}
          />
        );
      })}
    </ImageTable>
  );
}

const ForwardedApiImageTable = forwardRef(ApiImageTable);

export default ForwardedApiImageTable;
