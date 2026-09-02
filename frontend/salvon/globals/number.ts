import {
  numberFormat,
  numberFormatPrefix,
  numberFormatSuffix,
  randomNumberFromRange,
} from '@salvon/utils/number';

declare global {
  interface Number {
    numberFormat(decimals?: number, locale?: Intl.LocalesArgument): string;
    numberFormatPrefix(
      prefix: string,
      decimals?: number,
      locale?: Intl.LocalesArgument,
    ): string;
    numberFormatSuffix(
      suffix: string,
      decimals?: number,
      locale?: Intl.LocalesArgument,
    ): string;
  }

  interface NumberConstructor {
    randomFromRange(min?: number, max?: number, asInt?: boolean): number;
  }
}

Number.prototype.numberFormat = function (
  decimals = 2,
  locale: Intl.LocalesArgument = 'pl-PL',
): string {
  return numberFormat(this as number, decimals, locale);
};

Number.prototype.numberFormatPrefix = function (
  prefix: string,
  decimals = 2,
  locale: Intl.LocalesArgument = 'pl-PL',
): string {
  return numberFormatPrefix(this as number, prefix, decimals, locale);
};

Number.prototype.numberFormatSuffix = function (
  prefix: string,
  decimals = 2,
  locale: Intl.LocalesArgument = 'pl-PL',
): string {
  return numberFormatSuffix(this as number, prefix, decimals, locale);
};

Number.randomFromRange = function (
  min: number = 0,
  max: number = 100000,
  asInt: boolean = true,
): number {
  return randomNumberFromRange(min, max, asInt);
};
