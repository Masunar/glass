import kebabCase from 'lodash/kebabCase';

import { startingEndingSlashes } from '@salvon/consts/regex';

export const sanitize = (value: string): string => {
  return (
    value
      .normalize('NFD')
      // Remove language special chars (ąśćź etc.)
      .replace(/[\u0300-\u036f]/g, '')
      // Keep only alphanumeric, spaces, _, and - | replaces everything that is NOT listed here (^ char)
      .replace(/[^a-zA-Z0-9 .+_-]/g, '')
  );
};

export const sanitizeFilename = (
  value: string,
  separator: string = '_',
): string => {
  return sanitize(value).replace(/ /g, separator).toLowerCase();
};

export const capitalizeFirstChar = (value: string): string => {
  return value.charAt(0).toUpperCase() + value.slice(1);
};

export const removeHtmlTags = (htmlString?: string): string => {
  if (!htmlString) {
    return '';
  }

  return htmlString.replace(/<[^>]*>/g, '');
};

export const trimSlashes = (string: string): string => {
  return string.replace(startingEndingSlashes, '');
};

export const randomString = (
  length: number,
  allowedChars: undefined | string = undefined,
): string => {
  const characters =
    allowedChars ??
    'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

  const charactersLength = characters.length;

  let result = '';
  for (let i = 0; i < length; i++) {
    result += characters.charAt(Math.floor(Math.random() * charactersLength));
  }

  return result;
};

export const slugify = (value: string): string => kebabCase(value);

export const slug = (value: string, separator: string = '-'): string => {
  const endsWithBoundary = /[\s\-_.+]$/.test(value);

  const result = sanitize(value)
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, separator)
    .replace(new RegExp(`^${separator}+`), '')
    .replace(new RegExp(`${separator}+$`), '');

  if (endsWithBoundary && result.length > 0) {
    return `${result}${separator}`;
  }

  return result;
};

export const htmlSlug = (value: string): string => {
  const suffix = Date.now().toString().slice(6);
  return `${slug(value)}-${suffix}.html`;
};
