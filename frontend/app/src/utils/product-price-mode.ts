import type { TFunction } from 'i18next';

export const getProductPriceModeOptions = (t: TFunction) => [
  { label: t('product_price_mode.static'), value: 'static' },
  { label: t('product_price_mode.dynamic'), value: 'dynamic' },
];
