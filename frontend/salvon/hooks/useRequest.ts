import { useEffect, useRef, useState } from 'react';

import { AsyncApiRequest } from '@salvon/api';
import type { ResponseProps } from '@salvon/request';

export const useRequest = (
  request: () => Promise<AsyncApiRequest>,
  preventStrictMode: boolean = false,
) => {
  const ref = useRef(false);
  const [loading, setLoading] = useState(false);
  const [response, setResponse] = useState<undefined | ResponseProps<any>>(
    undefined,
  );
  const handleData = async () => {
    setLoading(true);
    const res: any = await request();
    setLoading(false);
    setResponse(res);
  };

  useEffect(() => {
    if (preventStrictMode) {
      if (ref.current) {
        return;
      }

      ref.current = true;
    }

    handleData();
  }, []);

  const content = response?.content ?? null;
  const res = response?.response ?? null;
  const error = response?.error ?? null;
  const data = content?.data ?? null;
  const items = data?.items ?? null;

  return { response: res, loading, content, data, error, items };
};
