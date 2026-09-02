import { useContext } from 'react';

import MenuControlContext from '@salvon/context/MenuControlContext';

export const useMenuControl = () => useContext(MenuControlContext);
