import { useState } from 'react';

import { useSearchParam } from '@salvon/hooks/useSearchParams';
import { randomString } from '@salvon/utils/string';
import { type UrlChangeMode, setSearchParams } from '@salvon/utils/url';

const randomParamName = randomString(5, 'abcdefghijklmnopqrstuvwxyz');

export const useSearchParamState = (
  defaultValue: string,
  searchParam?: string | false,
  keepExistingSearchParams: boolean = true,
  urlChangeMode: UrlChangeMode = 'replace',
) => {
  const param = useSearchParam(searchParam || randomParamName);

  const stateDefault = !!searchParam ? (param ?? defaultValue) : defaultValue;

  const [activeTab, setActiveTab] = useState<string>(stateDefault);

  const setValue = (v: string) => {
    if (!!searchParam) {
      setSearchParams(
        { [searchParam]: v },
        keepExistingSearchParams,
        urlChangeMode,
      );
    }

    setActiveTab(v);
  };

  return [activeTab, setValue] as [string, (v: string) => void];
};
