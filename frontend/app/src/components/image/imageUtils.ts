export const getPhoto = (
  variants: any,
  key: string,
  disableFallback: boolean = false,
): string => {
  return (
    variants?.[key] ||
    (disableFallback
      ? ''
      : !!variants
        ? (Object.values(variants)[0] as string)
        : '')
  );
};

export const getPhotoVariant = (url: string, variant: string) => {
  if (!url) return undefined;

  return `${url}/${variant}`;
};

export const photoVariants = {
  BG: 'bg',
  PUBLIC: 'public',
  SMALL: 'small',
  THUMBNAIL: 'thumbnail',
  HERO: 'hero',
  OG: 'og',
};

export const addVariantToUrl = (
  url: string | undefined,
  variant: string,
  overwrite = false,
): string => {
  if (!url) return '';

  try {
    const parsedUrl = new URL(url);
    const pathSegments = parsedUrl.pathname.split('/').filter(Boolean);
    const lastSegment = pathSegments[pathSegments.length - 1];

    const existingVariant = Object.values(photoVariants).includes(lastSegment);

    if (existingVariant) {
      if (overwrite) {
        pathSegments[pathSegments.length - 1] = variant;
      }
    } else {
      pathSegments.push(variant);
    }

    parsedUrl.pathname = '/' + pathSegments.join('/');
    return parsedUrl.toString();
  } catch {
    return url;
  }
};
