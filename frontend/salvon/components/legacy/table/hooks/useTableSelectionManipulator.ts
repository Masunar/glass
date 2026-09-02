import TableContext from '../context/TableSelectionContext';
import { useContext } from 'react';

export const useTableSelectionManipulator = () => useContext(TableContext);

export const useSelected = () => useTableSelectionManipulator().selected;

export const useAddSelection = () =>
  useTableSelectionManipulator().addSelection;
