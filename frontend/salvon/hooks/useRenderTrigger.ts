import dayjs from 'dayjs';
import { useState } from 'react';

const generateKey = () => dayjs().unix();

export const useRenderTrigger = (): [number | null, () => void] => {
  const [refresherKey, setRefresherKey] = useState<number>(generateKey());

  const refresh = () => setRefresherKey(generateKey());

  return [refresherKey, refresh];
};
