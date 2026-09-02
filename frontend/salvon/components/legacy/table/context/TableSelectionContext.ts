import { createContext } from 'react';

interface ContextInterface {
  selected: Array<any>;
  totalSelected: number;
  isSelected: (item: any) => boolean;
  anySelected: boolean;
  addSelection: (item: any) => void;
  removeSelection: (item: any) => void;
  clearSelected: () => void;
  allOnPageSelected: boolean;
  anyOnPageSelected: boolean;
  toggleAllOnPage: () => void;
  clearSelectedOnPage: () => void;
  selectAllOnPage: () => void;
  reloadTable: () => void;
  resetPage: () => void;
}

export const Context = createContext<ContextInterface>({
  selected: [],
  totalSelected: 0,
  isSelected: (_item: any) => false,
  anySelected: false,
  addSelection: (_item: any) => {},
  removeSelection: (_item: any) => {},
  clearSelected: () => {},
  allOnPageSelected: false,
  anyOnPageSelected: false,
  toggleAllOnPage: () => {},
  clearSelectedOnPage: () => {},
  selectAllOnPage: () => {},
  reloadTable: () => {},
  resetPage: () => {},
});

export default Context;
