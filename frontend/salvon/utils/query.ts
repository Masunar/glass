import { getWindow } from './ssr';

/**
 * Odczyt i zapis parametrow adresu.
 *
 * Wszystkie funkcje przechodza przez getWindow(): render po stronie
 * serwera nie ma obiektu window, a komponenty czytaja parametry juz przy
 * pierwszym renderze. Bez tego kazdy ekran oparty o ApiTable konczyl sie
 * "window is not defined" i strona 500 zamiast tabeli.
 */
const currentSearchParams = (): URLSearchParams | undefined => {
  const target = getWindow();

  if (target === undefined) {
    return undefined;
  }

  return new URLSearchParams(new URL(target.location.href).search);
};

export const setQueryParam = (param: string, value: string) => {
  const target = getWindow();

  if (target === undefined) {
    return;
  }

  const currentUrl = new URL(target.location.href);
  const searchParams = new URLSearchParams(currentUrl.search);

  setSearchParam(searchParams, param, value);

  currentUrl.search = searchParams.toString();
  target.history.replaceState({}, '', currentUrl.toString());
};

export const setSearchParam = (
  searchParams: URLSearchParams,
  param: string,
  value: string,
) => {
  if (searchParams.get(param) === String(value)) {
    return;
  }

  if (value.length === 0) {
    searchParams.delete(param);
  } else {
    searchParams.set(param, value);
  }
};

export const readQueryParam = (
  param: string,
  defaultValue: string = '',
): string => {
  return currentSearchParams()?.get(param) ?? defaultValue;
};

export const readQueryParamArray = (
  param: string,
  defaultValue: Array<string> = [],
): Array<string> => {
  return currentSearchParams()?.get(param)?.split(',') ?? defaultValue;
};
