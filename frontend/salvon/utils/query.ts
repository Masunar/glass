export const setQueryParam = (param: string, value: string) => {
  const currentUrl = new URL(window.location.href);
  const searchParams = new URLSearchParams(currentUrl.search);

  setSearchParam(searchParams, param, value);

  currentUrl.search = searchParams.toString();
  window.history.replaceState({}, '', currentUrl.toString());
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
  const currentUrl = new URL(window.location.href);
  const searchParams = new URLSearchParams(currentUrl.search);

  return searchParams.get(param) ?? defaultValue;
};

export const readQueryParamArray = (
  param: string,
  defaultValue: Array<string> = [],
): Array<string> => {
  const currentUrl = new URL(window.location.href);
  const searchParams = new URLSearchParams(currentUrl.search);

  return searchParams.get(param)?.split(',') ?? defaultValue;
};
