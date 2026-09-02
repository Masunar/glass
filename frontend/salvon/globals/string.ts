import {
  capitalizeFirstChar,
  randomString,
  removeHtmlTags,
  sanitize,
  sanitizeFilename,
  trimSlashes,
} from '@salvon/utils/string';

declare global {
  interface String {
    sanitize(): string;
    toFilename(spaceSeparator?: string): string;
    capitalize(): string;
    removeHtmlTags(): string;
    trimSlashes(): string;
  }

  interface StringConstructor {
    random(length: number, allowedChars?: undefined | string): string;
  }
}

String.prototype.sanitize = function (): string {
  return sanitize(this as string);
};

String.prototype.toFilename = function (separator: string = '_'): string {
  return sanitizeFilename(this as string, separator);
};

String.prototype.capitalize = function (): string {
  return capitalizeFirstChar(this as string);
};

String.prototype.removeHtmlTags = function (): string {
  return removeHtmlTags(this as string);
};

String.prototype.trimSlashes = function (): string {
  return trimSlashes(this as string);
};

String.random = function (
  length: number,
  allowedChars: undefined | string = undefined,
): string {
  return randomString(length, allowedChars);
};
