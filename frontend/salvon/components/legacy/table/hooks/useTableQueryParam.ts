import { type Dispatch, type SetStateAction, useState } from 'react';

import { readQueryParam } from '@salvon/utils/query';

export const useTableQueryParam = <T extends string>(
  paramName: string,
  defaultState: T,
): [string, Dispatch<SetStateAction<any>>, any] => {
  const [value, dispatch] = useState<string>(
    readQueryParam(paramName, String(defaultState)),
  );

  return [
    value,
    dispatch,
    {
      name: paramName,
      value: value,
      callable: dispatch,
      defaultValue: defaultState,
    },
  ];
};
