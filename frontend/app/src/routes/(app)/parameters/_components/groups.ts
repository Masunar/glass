/**
 * Podział parametrów na sekcje ekranu.
 *
 * Stary ekran „Ogólne" był jedną listą kilkunastu pól bez żadnego
 * porządku — parametry wzoru wyceny leżały wymieszane z tekstami
 * drukowanymi na ofercie, mimo że zmienia je kto inny i w innym celu.
 */
export const parameterGroups: {
  titleKey: string;
  leadKey?: string;
  keys: string[];
}[] = [
  {
    titleKey: 'page.parameters.group.pricing',
    keys: [
      'min_billable_m2_tempered',
      'min_billable_m2_untempered',
      'oversize_threshold_m2',
      'oversize_surcharge_percent',
      'shape_surcharge_percent',
      'min_pane_price',
      'min_pane_surcharge_percent',
    ],
  },
  {
    titleKey: 'page.parameters.group.method',
    leadKey: 'page.parameters.method_lead',
    keys: ['surcharge_mode', 'min_price_check'],
  },
  {
    titleKey: 'page.parameters.group.limits',
    keys: ['max_pane_width_mm', 'max_pane_height_mm', 'assembly_duration_days'],
  },
  {
    titleKey: 'page.parameters.group.offer',
    keys: [
      'offer_validity_days',
      'offer_validity_text',
      'offer_payment_terms',
      'offer_delivery_time',
      'bank_account_iban',
    ],
  },
];
