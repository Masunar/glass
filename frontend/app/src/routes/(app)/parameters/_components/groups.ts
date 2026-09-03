/**
 * Podział parametrów na zakładki ekranu.
 *
 * Stary ekran „Ogólne" był jedną listą kilkunastu pól bez porządku —
 * parametry wzoru wyceny leżały wymieszane z tekstami drukowanymi na
 * ofercie, mimo że zmienia je kto inny i w innym celu.
 *
 * Zakładki zamiast czterech sekcji pod sobą: wszystkie razem dawały
 * ekran na dwa i pół obrotu kółkiem, a zmiana wzoru wyceny i zmiana
 * tekstu na ofercie to dwie różne wizyty na tym ekranie, nie jedna.
 *
 * `columns` mówi, ile pól mieści się w rzędzie. Progi i dopłaty to
 * krótkie liczby — trzymanie ich w jednej kolumnie na pełną szerokość
 * ekranu było marnowaniem miejsca i wymuszało przewijanie.
 */
export const parameterGroups: {
  key: string;
  titleKey: string;
  leadKey?: string;
  columns: 1 | 2;
  keys: string[];
}[] = [
  {
    key: 'pricing',
    titleKey: 'page.parameters.group.pricing',
    columns: 2,
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
    key: 'method',
    titleKey: 'page.parameters.group.method',
    leadKey: 'page.parameters.method_lead',
    columns: 1,
    keys: ['surcharge_mode', 'min_price_check'],
  },
  {
    key: 'limits',
    titleKey: 'page.parameters.group.limits',
    columns: 2,
    keys: ['max_pane_width_mm', 'max_pane_height_mm', 'assembly_duration_days'],
  },
  {
    key: 'offer',
    titleKey: 'page.parameters.group.offer',
    // Teksty ofertowe bywają długie — dwie kolumny obcinałyby je
    // w połowie i nie dałoby się ich przeczytać przed zapisem.
    columns: 1,
    keys: [
      'offer_validity_days',
      'offer_validity_text',
      'offer_payment_terms',
      'offer_delivery_time',
      'bank_account_iban',
    ],
  },
];
