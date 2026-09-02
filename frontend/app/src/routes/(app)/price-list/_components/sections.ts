/**
 * Zakładki cennika = sekcje asortymentu.
 *
 * Ten sam podział wraca w kartotece kontrahenta, w rabatach na zleceniu
 * i w magazynie — stąd jedna kartoteka produktów z dyskryminatorem,
 * a nie pięć równoległych.
 */
export const priceListSections = [
  { value: 'glass', labelKey: 'page.price_list.section.glass' },
  { value: 'fittings', labelKey: 'page.price_list.section.fittings' },
  { value: 'frames', labelKey: 'page.price_list.section.frames' },
  { value: 'services', labelKey: 'page.price_list.section.services' },
  { value: 'other', labelKey: 'page.price_list.section.other' },
] as const;

/** Marża na cenie sprzedaży wynika z samego współczynnika: 1 − 1/wsp. */
export function marginFromCoefficient(coefficient: string): number | null {
  const value = Number(coefficient.replace(',', '.'));

  if (!Number.isFinite(value) || value <= 0) {
    return null;
  }

  return Math.round((1 - 1 / value) * 1000) / 10;
}

export function computePrice(
  purchase: string | null,
  coefficient: string,
): string | null {
  const factor = Number(coefficient.replace(',', '.'));

  if (purchase === null || !Number.isFinite(factor) || factor <= 0) {
    return null;
  }

  return (Math.round(Number(purchase) * factor * 100) / 100).toFixed(2);
}
