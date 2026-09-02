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
