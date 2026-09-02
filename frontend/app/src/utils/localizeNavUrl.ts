export function localizeNavUrl(
  url: string | null | undefined,
  locale: string,
  urlTranslations?: Record<string, string> | null,
): string {
  if (locale !== 'pl' && urlTranslations?.[locale]) {
    return urlTranslations[locale];
  }
  if (!url) return '#';
  if (locale === 'pl' || !url.startsWith('/')) return url;
  if (url === '/produkty') return '/en/products';
  if (url.startsWith('/produkty/')) return `/en/products/${url.slice(10)}`;
  if (url.startsWith('/produkty?')) return `/en/products${url.slice(8)}`;
  return `/en${url}`;
}
