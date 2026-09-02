import TableContext from '../context/TableContext';
import { type ReactNode, useState } from 'react';

interface Props {
  children: ReactNode;
  data: any;
}

export default function TableProvider({ children, data }: Props) {
  const [currentlySelected, setCurrentlySelected] = useState<Array<any>>([]);

  const addSelection = (item: any) =>
    setCurrentlySelected((currentlySelectedItems) => {
      return [...currentlySelectedItems, item];
    });

  const removeSelection = (item: any) => {
    setCurrentlySelected((currentlySelectedItems) => {
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
    setCurrentlySelected((selected) => {
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
    setCurrentlySelected((selected) => {
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

  const isSelected = (item: any) => {
    return !!currentlySelected.filter((filterItem) => {
      return item.id == filterItem.id;
    }).length;
  };

  const totalSelected = currentlySelected.length;

  return (
    <TableContext.Provider
      value={{
        tableData: data,
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
      }}
    >
      {children}
    </TableContext.Provider>
  );
}
