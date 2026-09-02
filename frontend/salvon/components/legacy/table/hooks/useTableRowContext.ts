import TableRowContext from '../context/TableRowContext';
import { useContext } from 'react';

export const useTableRowContext = () => useContext(TableRowContext);

export const useRowData = (): any => useTableRowContext().rowData;
export const useReloadRow = (): any => useTableRowContext().reloadRow;
