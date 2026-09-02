import { jsonDecode, jsonEncode } from '@salvon/utils/json';

export const getFromLocalStorage = (
  key: string,
  defaultValue: any = undefined,
): any => {
  try {
    const storedItem = window.localStorage.getItem(key);
    return storedItem !== null ? jsonDecode(storedItem) : defaultValue;
  } catch {
    return undefined;
  }
};

export const saveToLocalStorage = (key: string, value: any) => {
  window.localStorage.setItem(key, jsonEncode(value));
};

export const removeLocalStored = (key: string) => {
  window.localStorage.removeItem(key);
};
