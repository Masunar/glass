/**
 * Dokąd wrócić po zalogowaniu.
 *
 * Dwie rzeczy, które muszą być odsiane, zanim adres trafi do
 * `window.location`:
 *
 * 1. Sufiks `.data`. React Router pobiera dane osobnym żądaniem pod
 *    adresem z tym sufiksem, więc gdy wygaśnięcie sesji złapie właśnie
 *    takie żądanie, `return_to` wskazuje na `/price-list.data` albo
 *    `/_.data` — i po zalogowaniu użytkownik ląduje na surowym
 *    strumieniu danych zamiast na ekranie.
 *
 * 2. Adres spoza aplikacji. `return_to` przychodzi z paska adresu,
 *    więc `?return_to=https://…` po zalogowaniu wyrzuciłoby użytkownika
 *    na obcą stronę. Z ekranu logowania to gotowy phishing, dlatego
 *    przechodzą wyłącznie ścieżki wewnętrzne.
 */
export function stripDataSuffix(path: string): string {
  const withoutSuffix = path.replace(/\.data$/, '');

  // Zadanie o dane dla trasy głównej ma postać `/_` albo `/_root`.
  return withoutSuffix.replace(/^\/_(root)?$/, '/');
}

export function safeReturnTo(
  value: string | null | undefined,
  fallback: string = '/',
): string {
  if (!value) {
    return fallback;
  }

  // `//host` jest adresem bezwzględnym mimo wiodącego ukośnika.
  if (!value.startsWith('/') || value.startsWith('//')) {
    return fallback;
  }

  const [path, query] = value.split('?');
  const safePath = stripDataSuffix(path);

  if (safePath === '/' && !query) {
    return fallback;
  }

  return query ? `${safePath}?${query}` : safePath;
}
