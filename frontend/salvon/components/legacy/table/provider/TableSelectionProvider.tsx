import TableContext from '../context/TableSelectionContext';
import { type ReactNode } from 'react';

interface Props {
  children: ReactNode;
  reloadTable: () => void;
  resetPage: () => void;
  data: any;
  currentlySelected: Array<any>;
  setCurrentlySelected: (data: any) => void;
}

export default function TableSelectionProvider({
  children,
  data,
  reloadTable,
  resetPage,
  currentlySelected,
  setCurrentlySelected,
}: Props) {
  const addSelection = (item: any) =>
    setCurrentlySelected((currentlySelectedItems: Array<any>) => {
      return [...currentlySelectedItems, item];
    });

  const removeSelection = (item: any) => {
    setCurrentlySelected((currentlySelectedItems: Array<any>) => {
      return currentlySelectedItems.filter(
        (filterItem) => filterItem.id !== item.id,
      );
    });
  };

  const clearSelected = () => setCurrentlySelected([]);

  const anySelected = !!currentlySelected.length;

  const matchedOnPage = data.filter((value: { id: any }) =>
    currentlySelected.some((item) => item.id === value.id),
  );

  const matchedOnPageLength = matchedOnPage.length;

  const anyOnPageSelected = !!matchedOnPageLength;

  const allOnPageSelected = data.length && matchedOnPageLength === data.length;

  const selectAllOnPage = () => {
    setCurrentlySelected((selected: Array<any>) => {
      const notMatchedOnPage = data.filter(
        (item: { id: number }) =>
          !matchedOnPage.some(
            (matchedItem: { id: number }) => matchedItem.id === item.id,
          ),
      );

      return [...selected, ...notMatchedOnPage];
    });
  };

  const clearSelectedOnPage = () => {
    setCurrentlySelected((selected: Array<any>) => {
      return selected.filter(
        (item: { id: number }) =>
          !matchedOnPage.some(
            (matchedItem: { id: number }) => matchedItem.id === item.id,
          ),
      );
    });
  };

  const toggleAllOnPage = () => {
    if (matchedOnPage.length === data.length) {
      clearSelectedOnPage();
      return;
    }

    selectAllOnPage();
  };

  const isSelected = (item: any): boolean => {
    return !!currentlySelected.filter((filterItem) => {
      return item.id == filterItem.id;
    }).length;
  };

  const totalSelected = currentlySelected.length;

  return (
    <TableContext.Provider
      value={{
        selected: currentlySelected,
        totalSelected,
        isSelected,
        anySelected,
        addSelection,
        removeSelection,
        clearSelected,
        allOnPageSelected,
        anyOnPageSelected,
        toggleAllOnPage,
        clearSelectedOnPage,
        selectAllOnPage,
        reloadTable,
        resetPage,
      }}
    >
      {children}
    </TableContext.Provider>
  );
}
