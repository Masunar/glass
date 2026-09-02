import { createContext } from 'react';

interface ContextInterface {
  tableData: Array<any>;
  anySelected: boolean;
  allOnPageSelected: boolean;
  anyOnPageSelected: boolean;
  totalSelected: number;
  selected: any[];
  addSelection: (item: any) => void;
  isSelected: (item: any) => boolean;
  removeSelection: (item: any) => void;
  clearSelected: () => void;
  selectAllOnPage: () => void;
  clearSelectedOnPage: () => void;
  toggleAllOnPage: () => void;
}

export const Context = createContext<ContextInterface>({
  tableData: [],
  anySelected: false,
  allOnPageSelected: false,
  anyOnPageSelected: false,
  totalSelected: 0,
  selected: [],
  isSelected: () => false,
  addSelection: () => {},
  removeSelection: () => {},
  clearSelected: () => {},
  selectAllOnPage: () => {},
  clearSelectedOnPage: () => {},
  toggleAllOnPage: () => {},
});

export default Context;
