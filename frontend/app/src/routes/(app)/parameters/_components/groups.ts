/**
 * Podział parametrów na pasma i zakładki.
 *
 * Stary ekran „Ogólne" był jedną listą kilkunastu pól bez porządku —
 * parametry wzoru wyceny leżały wymieszane z tekstami drukowanymi na
 * ofercie, mimo że zmienia je kto inny i w innym celu.
 *
 * `appliesTo` to nie opis parametru, tylko odpowiedź na pytanie „czego
 * to dotknie". Bez niej zmiana minimum powierzchni jest liczbą bez
 * konsekwencji — a dotyczy wyłącznie szkła hartowanego.
 */
export type ParameterBandKey = 'minimum' | 'surcharge' | 'limits' | 'offer';

export const parameterBands: {
  key: ParameterBandKey;
  titleKey: string;
  leadKey?: string;
  /** Odcień pasma: znaczenie, nie ozdoba. */
  tone: 'module' | 'money' | 'plain';
  keys: string[];
}[] = [
  {
    key: 'minimum',
    titleKey: 'page.parameters.band.minimum',
    leadKey: 'page.parameters.band.minimum_lead',
    tone: 'module',
    keys: ['min_billable_m2_tempered', 'min_billable_m2_untempered'],
  },
  {
    key: 'surcharge',
    titleKey: 'page.parameters.band.surcharge',
    leadKey: 'page.parameters.band.surcharge_lead',
    tone: 'money',
    keys: [
      'oversize_surcharge_percent',
      'shape_surcharge_percent',
      'min_pane_surcharge_percent',
      'surcharge_mode',
      'min_price_check',
    ],
  },
  {
    key: 'limits',
    titleKey: 'page.parameters.band.limits',
    tone: 'plain',
    keys: [
      'oversize_threshold_m2',
      'min_pane_price',
      'max_pane_width_mm',
      'max_pane_height_mm',
      'assembly_duration_days',
    ],
  },
  {
    key: 'offer',
    titleKey: 'page.parameters.band.offer',
    tone: 'plain',
    keys: [
      'offer_validity_days',
      'offer_validity_text',
      'offer_payment_terms',
      'offer_delivery_time',
      'bank_account_iban',
    ],
  },
];

/** Zakładki filtrują listę; pasma zostają w środku każdej. */
export const parameterTabs: {
  key: string;
  labelKey: string;
  bands: ParameterBandKey[];
}[] = [
  {
    key: 'all',
    labelKey: 'page.parameters.tab.all',
    bands: ['minimum', 'surcharge', 'limits', 'offer'],
  },
  {
    key: 'pane',
    labelKey: 'page.parameters.tab.pane',
    bands: ['minimum', 'surcharge'],
  },
  { key: 'limits', labelKey: 'page.parameters.tab.limits', bands: ['limits'] },
  { key: 'offer', labelKey: 'page.parameters.tab.offer', bands: ['offer'] },
];

/** Jednostka dopisywana za wartością. Pusta tam, gdzie liczba jest bezwymiarowa. */
export const parameterUnits: Record<string, string> = {
  min_billable_m2_tempered: 'm²',
  min_billable_m2_untempered: 'm²',
  oversize_threshold_m2: 'm²',
  min_pane_price: 'zł',
  max_pane_width_mm: 'mm',
  max_pane_height_mm: 'mm',
  assembly_duration_days: 'dni',
  offer_validity_days: 'dni',
};
