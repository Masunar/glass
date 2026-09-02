import Context from '../context/TableRowContext';
import { type ReactNode } from 'react';

interface Props {
  children: ReactNode;
  reloadRow: (onFinish?: () => void) => void;
  rowData: any;
}

export default function TableRowProvider({
  children,
  rowData,
  reloadRow,
}: Props) {
  return (
    <Context.Provider value={{ rowData, reloadRow }}>
      {children}
    </Context.Provider>
  );
}
