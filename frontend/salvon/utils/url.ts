import { voc } from '@salvon/utils/object';

export type UrlChangeMode = 'push' | 'replace';

export const setSearchParams = (
  values: {
    [key: string]: string;
  },
  keepExisting: boolean = true,
  mode: UrlChangeMode = 'replace',
) => {
  const { origin, pathname, search } = window.location;

  const searchParamsEntries = new URLSearchParams(search).entries();
  const oldParams = Object.fromEntries(searchParamsEntries);

  const url = new URL(origin + pathname);
  const searchParams = new URLSearchParams({
    ...voc(keepExisting, oldParams),
    ...values,
  });

  url.search = searchParams.toString();

  if (mode === 'push') {
    window.history.pushState({}, '', url.toString());
    return;
  }

  window.history.replaceState({}, '', url.toString());
};
