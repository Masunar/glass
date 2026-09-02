import TRow from '../../DataTable/TRow';
import type {
  CellRenderer,
  Column,
  RowRenderer as Props,
  TableActions,
} from '../../Interfaces';
import TableRowProvider from '../../provider/TableRowProvider';
import Desktop from './Variants/Desktop';
import Mobile from './Variants/Mobile';
import {
  type FunctionComponent,
  createElement,
  useEffect,
  useState,
} from 'react';

import PositiveNegative from '@salvon/components/chip/PositiveNegative';
import { Flex } from '@salvon/components/div';
import { useTranslation } from '@salvon/hooks/useTranslation';
import type { ResponseProps } from '@salvon/request';
import { isAccessDenied } from '@salvon/utils/api';
import { notifyError } from '@salvon/utils/notify';
import { objectValueFromPath } from '@salvon/utils/object';
import { isBoolean } from '@salvon/utils/type-check';

export default function RowRenderer({
  row,
  columns,
  rowProps,
  apiClass,
  apiMethod,
  reloadTable,
  checkboxSelection,
  rowCheckerProps,
  withRowContext = false,
  additionalApiQueryParams = {},
  index,
  mobile = false,
}: Props) {
  const t = useTranslation();
  const [isFirstRender, setIsFirstRender] = useState<boolean>(true);
  const [currentRow, setRow] = useState<any>(row);

  useEffect(() => {
    setRow(row);
  }, [row]);

  const reloadRow = async (onFinish?: () => void) => {
    if (isFirstRender) {
      setIsFirstRender(false);
    }

    if (!row.id) {
      throw new Error('Trying to use re-rendering on row without id property.');
    }

    const onFinishCallback = onFinish ? onFinish : () => {};

    apiClass[apiMethod](
      { ids: [row.id] },
      handleResponse,
      onFinishCallback,
      additionalApiQueryParams,
    );

    const res = await apiClass[apiMethod]({
      ids: [row.id],
      ...additionalApiQueryParams,
    });

    handleResponse(res);
    onFinishCallback();
  };

  const handleResponse = ({ content, response }: ResponseProps<any>) => {
    if (isAccessDenied(response)) {
      return;
    }

    if (!response.success) {
      notifyError(t('api.ise'));
      return;
    }

    const fetchedRow = content?.data?.items[0];

    if (!fetchedRow) {
      reloadTable();
      return;
    }

    setRow(fetchedRow);
  };

  const renderStandalone = (row: any, columnName: string) => {
    if (columnName.split('.').length > 1) {
      return formatStandaloneValue(objectValueFromPath<any>(row, columnName));
    }

    return formatStandaloneValue(row[columnName]);
  };

  const formatStandaloneValue = (value: any) => {
    if (isBoolean(value)) {
      return (
        <PositiveNegative condition={value} sx={{ borderRadius: '6px' }} />
      );
    }
    return value;
  };

  const renderActions = (actions: TableActions) => {
    return (
      <Flex gap={0.5} jEnd={!mobile}>
        {actions.map(
          (action: FunctionComponent<CellRenderer>, iterator: number) => {
            return createElement(action, {
              key: iterator,
              row: currentRow,
              reloadRow,
              reloadTable,
              t,
            });
          },
        )}
      </Flex>
    );
  };

  const renderCell = (column: Column) => {
    if (column.render) {
      return column.render({
        row: currentRow,
        index: index,
        reloadTable,
        reloadRow,
        t,
      });
    }

    if (column.actions) {
      return renderActions(column.actions);
    }

    return renderStandalone(currentRow, column.name);
  };

  const { click, doubleClick, rowCheckerDisabled, ...restRowProps } =
    rowProps ?? {};

  let rowRenderer = mobile ? (
    <Mobile
      checkboxSelection={checkboxSelection}
      columns={columns}
      row={row}
      rowCheckerDisabled={rowCheckerDisabled}
      rowCheckerProps={rowCheckerProps}
      renderCell={renderCell}
    />
  ) : (
    <Desktop
      checkboxSelection={checkboxSelection}
      columns={columns}
      row={row}
      rowCheckerDisabled={rowCheckerDisabled}
      rowCheckerProps={rowCheckerProps}
      renderCell={renderCell}
    />
  );

  if (withRowContext) {
    rowRenderer = (
      <TableRowProvider reloadRow={reloadRow} rowData={row}>
        {rowRenderer}
      </TableRowProvider>
    );
  }

  return (
    <TRow
      onClick={() => click && click(row)}
      onDoubleClick={() => doubleClick && doubleClick(row)}
      {...restRowProps}
    >
      {rowRenderer}
    </TRow>
  );
}
