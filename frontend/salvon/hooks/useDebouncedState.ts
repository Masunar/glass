import {
  type Dispatch,
  type SetStateAction,
  useEffect,
  useRef,
  useState,
} from 'react';

type UseDebouncedStateReturn<T> = [T, T, Dispatch<SetStateAction<T>>];

export const useDebouncedState = <T>(
  delay: number,
  initialValue: T = undefined as any,
): UseDebouncedStateReturn<T> => {
  const [value, setValue] = useState<T>(initialValue);
  const [debouncedValue, setDebouncedValue] = useState<T>(initialValue);
  const timeoutRef = useRef<ReturnType<typeof setTimeout>>(
    setTimeout(() => {}),
  );

  useEffect(() => {
    if (timeoutRef.current) clearTimeout(timeoutRef.current);
    timeoutRef.current = setTimeout(() => {
      setDebouncedValue(value);
    }, delay);

    return () => clearTimeout(timeoutRef.current);
  }, [value, delay]);

  return [value, debouncedValue, setValue];
};
