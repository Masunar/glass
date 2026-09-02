import TableContext from '../context/TableContext';
import { useContext } from 'react';

export const useTableManipulator = () => useContext(TableContext);

export const useSelected = () => useTableManipulator().selected;

export const useAddSelection = () => useTableManipulator().addSelection;
