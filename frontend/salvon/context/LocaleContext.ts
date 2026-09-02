import { createContext } from 'react';

import type { ContextSetStateAction } from '@salvon/types';

export type LocaleContextProps = {
  locale: string;
  setLocale: ContextSetStateAction<string>;
};

export const Context = createContext<LocaleContextProps>({
  locale: '',
  setLocale: () => undefined,
});

export default Context;
