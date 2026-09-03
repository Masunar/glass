/**
 * Tokeny design systemu Industry.
 *
 * Jedyne miejsce, w którym w aplikacji występują wartości kolorów.
 * Reguła z handoffu: żadnych hexów w komponentach — wszystko stąd.
 *
 * Rampy powstały w OKLCH na jednej skali jasności, więc ten sam stopień
 * dowolnej roli ma tę samą wartość wizualną. Stąd `neutral[300]`
 * i `accent[300]` da się zamieniać bez rozjazdu kontrastu.
 */
export const industry = {
  bg: '#f2f2f3',
  surface: '#ffffff',
  text: '#1d1f20',

  /** Stal — wyłącznie jako sygnał, nie jako dekoracja. */
  accent: {
    base: '#5980a6',
    100: '#eef6ff',
    200: '#d6ebff',
    300: '#b5d9fd',
    400: '#94bce3',
    500: '#749dc4',
    600: '#597ea3',
    700: '#416180',
    800: '#2c455d',
    900: '#1d2d3d',
  },

  neutral: {
    100: '#f5f5f8',
    200: '#e7e7ea',
    300: '#d4d4d7',
    400: '#b7b7ba',
    500: '#98989b',
    600: '#7a7a7d',
    700: '#5d5d60',
    800: '#424244',
    900: '#2b2b2d',
  },

  /** 16 % tuszu — linia włosowa, nie ramka. */
  divider: 'color-mix(in srgb, #1d1f20 16%, transparent)',

  font: {
    /** Numery, kwoty, wymiary, tytuły pasm. To on daje gęstość. */
    heading: '"Barlow Condensed", system-ui, sans-serif',
    body: '"Barlow", system-ui, sans-serif',
  },

  /** Zero wszędzie — w tym systemie nie ma kafelków. */
  radius: 0,

  shadow: {
    /** Jedyny dozwolony cień: menu wypływające nad treść. */
    md: '0 3px 10px color-mix(in srgb, #2b2b2d 16%, transparent)',
  },
} as const;

/**
 * Odcienie modułów — hue niesie znaczenie, nie dekorację.
 *
 * Wszystkie mają tę samą jasność i nasycenie, zmienia się wyłącznie
 * hue, więc kolory nie konkurują ze sobą: żaden nie jest „mocniejszy”
 * od pozostałych, a ekran nie krzyczy wszystkim naraz.
 *
 * ⚠️ Te wartości NIE MOGĄ trafić do palety MUI. Funkcje alpha(),
 * lighten() i darken() parsują kolor samodzielnie i wywracają się na
 * notacji CSS Color 4, a Salvon używa ich w wielu miejscach. Paleta MUI
 * zostaje na hexach z `industry`, a te odcienie żyją jako zmienne CSS
 * używane wprost w warstwie list i nawigacji.
 */
export const modules = {
  /** Zlecenia, sprzedaż — stal. To samo, co akcent systemu. */
  zlec: { base: 'oklch(0.58 0.12 250)', tint: 'oklch(0.94 0.03 250)' },
  /** Produkcja, hartownia — morski. */
  prod: { base: 'oklch(0.55 0.10 195)', tint: 'oklch(0.94 0.04 195)' },
  /** Magazyn, dostawy, „gotowe” — zielony. */
  mag: { base: 'oklch(0.55 0.10 145)', tint: 'oklch(0.94 0.04 145)' },
  /** Księgowość, pieniądze, limity — ochra. */
  ksie: { base: 'oklch(0.52 0.11 85)', tint: 'oklch(0.95 0.05 85)' },
  /** Raporty, statystyki — śliwka. */
  rap: { base: 'oklch(0.52 0.11 320)', tint: 'oklch(0.94 0.04 320)' },
  /** Zaległe, reklamacje — terakota. Nie jest modułem, tylko stanem. */
  alert: { base: 'oklch(0.46 0.13 25)', tint: 'oklch(0.96 0.02 25)' },
  /** Administracja — neutralny, bo nie niesie pilności. */
  adm: { base: 'oklch(0.52 0.01 250)', tint: 'oklch(0.94 0.00 250)' },
} as const;

export type ModuleKey = keyof typeof modules;

/** Ciemna listwa modułów — jedyne ciemne miejsce w jasnym motywie. */
/**
 * Panel boczny jako warstwa nad treścią.
 *
 * Nagłówek dzieli kolor z listwą modułów: to ta sama warstwa systemu,
 * więc nie ma powodu, żeby wyglądała inaczej.
 */
export const drawer = {
  headBg: 'oklch(0.24 0.03 250)',
  headFg: 'oklch(0.95 0.01 250)',
  headKicker: 'oklch(0.78 0.10 250)',
  switchBg: 'oklch(0.72 0.13 250)',
  switchFg: 'oklch(0.22 0.04 250)',
  scrim: 'oklch(0.24 0.03 250 / 0.28)',
  shadow: '-14px 0 36px oklch(0.24 0.03 250 / 0.22)',
} as const;

/**
 * Wyszukiwarka ogólna — ciemny panel nad przygaszonym tłem.
 *
 * Dzieli kolor z listwą modułów, bo obie warstwy stoją ponad treścią
 * i nie należą do żadnego ekranu z osobna.
 */
export const spotlight = {
  bg: 'oklch(0.24 0.03 250)',
  fg: 'oklch(0.95 0.01 250)',
  dim: 'oklch(0.68 0.02 250)',
  line: 'oklch(0.36 0.03 250)',
  hover: 'oklch(0.29 0.03 250)',
  accent: 'oklch(0.72 0.13 250)',
  shadow: '0 26px 70px oklch(0.18 0.03 250 / 0.5)',
} as const;

export const rail = {
  bg: 'oklch(0.24 0.03 250)',
  fg: 'oklch(0.95 0.01 250)',
  tile: 'oklch(0.34 0.04 250)',
  brandBg: 'oklch(0.72 0.13 250)',
  brandFg: 'oklch(0.22 0.04 250)',
} as const;
