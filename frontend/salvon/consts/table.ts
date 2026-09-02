export type OrderNames = 'asc' | 'desc';

type SortOrder = {
  asc: 'asc';
  desc: 'desc';
};

export const sort_order: SortOrder = {
  asc: 'asc',
  desc: 'desc',
};

export const default_sort_order = sort_order.asc;
