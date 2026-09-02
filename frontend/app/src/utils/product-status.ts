import type { TFunction } from 'i18next';

export type ProductStatus = 'draft' | 'not_published' | 'published';

export const getProductStatusOptions = (t: TFunction) => [
  { label: t('product_status.draft'), value: 'draft' },
  { label: t('product_status.not_published'), value: 'not_published' },
  { label: t('product_status.published'), value: 'published' },
];

export const getProductStatusColor = (
  status: ProductStatus,
): 'default' | 'warning' | 'success' => {
  switch (status) {
    case 'published':
      return 'success';
    case 'not_published':
      return 'warning';
    default:
      return 'default';
  }
};
