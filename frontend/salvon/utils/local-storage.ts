import { jsonDecode, jsonEncode } from '@salvon/utils/json';

export const getFromLocalStorage = (
  key: string,
  defaultValue: any = undefined,
): any => {
  // Na serwerze nie ma window, a renderowanie zaczyna sie wlasnie tam.
  // Zwrocenie undefined zamiast wartosci domyslnej wywracalo SSR: jezyk
  // aplikacji schodzil do undefined, a dayjs wolany z pustym jezykiem
  // zwraca nazwe jezyka zamiast daty.
  if (typeof window === 'undefined') {
    return defaultValue;
  }

  try {
    const storedItem = window.localStorage.getItem(key);
    return storedItem !== null ? jsonDecode(storedItem) : defaultValue;
  } catch {
    return defaultValue;
  }
};

export const saveToLocalStorage = (key: string, value: any) => {
  window.localStorage.setItem(key, jsonEncode(value));
};

export const removeLocalStored = (key: string) => {
  window.localStorage.removeItem(key);
};
