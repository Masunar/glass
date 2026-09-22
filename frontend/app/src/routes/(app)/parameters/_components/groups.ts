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
export type ParameterBandKey =
  | 'minimum'
  | 'surcharge'
  | 'vat'
  | 'limits'
  | 'seller'
  | 'offer';

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
    // Kolejnosc jak w `PaneCalculator::applySurcharges()`: ksztalt,
    // pilne, gabaryt, na koncu minimum. Ekran jej nie ustala — ustala
    // ja kod — ale rozjazd miedzy jednym a drugim mylil przy czytaniu.
    keys: [
      'shape_surcharge_percent',
      'urgent_surcharge_percent',
      'oversize_surcharge_percent',
      'min_pane_surcharge_percent',
      'surcharge_mode',
      'min_price_check',
    ],
  },
  {
    // Stawki i limity stoja osobno od doplat, bo nie licza ceny tylko
    // ja dziela: doplata zmienia kwote netto, VAT dzieli gotowa kwote
    // na dwie stawki. Pomieszanie tego na jednym pasmie sugerowaloby,
    // ze limit m2 wplywa na cene formatki. Nie wplywa.
    key: 'vat',
    titleKey: 'page.parameters.band.vat',
    leadKey: 'page.parameters.band.vat_lead',
    tone: 'money',
    keys: [
      'vat_reduced_rate',
      'vat_standard_rate',
      'vat_limit_m2_house',
      'vat_limit_m2_flat',
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
    // Dane sprzedawcy stoja osobno od tekstow ofertowych, bo zmienia
    // sie z innego powodu: tekst handlowiec poprawia, adres firmy
    // zmienia sie raz na kilka lat i dotyczy kazdego dokumentu, nie
    // tylko oferty.
    key: 'seller',
    titleKey: 'page.parameters.band.seller',
    leadKey: 'page.parameters.band.seller_lead',
    tone: 'plain',
    keys: [
      'company_name',
      'company_address',
      'company_tax_id',
      'company_phone',
      'company_email',
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
    bands: ['minimum', 'surcharge', 'vat', 'limits', 'seller', 'offer'],
  },
  {
    key: 'pane',
    labelKey: 'page.parameters.tab.pane',
    bands: ['minimum', 'surcharge'],
  },
  { key: 'vat', labelKey: 'page.parameters.tab.vat', bands: ['vat'] },
  { key: 'limits', labelKey: 'page.parameters.tab.limits', bands: ['limits'] },
  {
    key: 'offer',
    labelKey: 'page.parameters.tab.offer',
    bands: ['seller', 'offer'],
  },
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
  vat_limit_m2_house: 'm²',
  vat_limit_m2_flat: 'm²',
};
