import { createContext } from 'react';

interface ContextInterface {
  rowData: any;
  reloadRow: () => void;
}

export const Context = createContext<ContextInterface>({
  rowData: {},
  reloadRow: () => {},
});

export default Context;
