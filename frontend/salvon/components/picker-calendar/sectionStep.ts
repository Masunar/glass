import type { Dayjs } from 'dayjs';

export type Unit = 'year' | 'month' | 'day' | 'hour' | 'minute' | 'second';

export type Section = { start: number; end: number; unit: Unit };

export type RangeSection = Section & { part: 'start' | 'end' };

// Format tokens we support, longest-first so 'YYYY' wins over 'YY'.
const TOKENS: { token: string; unit: Unit }[] = [
  { token: 'YYYY', unit: 'year' },
  { token: 'YY', unit: 'year' },
  { token: 'MM', unit: 'month' },
  { token: 'M', unit: 'month' },
  { token: 'DD', unit: 'day' },
  { token: 'D', unit: 'day' },
  { token: 'HH', unit: 'hour' },
  { token: 'hh', unit: 'hour' },
  { token: 'H', unit: 'hour' },
  { token: 'h', unit: 'hour' },
  { token: 'mm', unit: 'minute' },
  { token: 'm', unit: 'minute' },
  { token: 'ss', unit: 'second' },
  { token: 's', unit: 'second' },
];

/**
 * Maps a rendered value string to its editable sections, by walking the format
 * and the value in lockstep. Non-token chars (separators, `A`, etc.) are skipped
 * in the format but advance the value cursor by their literal width.
 */
export function getSections(format: string, value: string): Section[] {
  const sections: Section[] = [];
  let fi = 0; // format cursor
  let vi = 0; // value cursor

  while (fi < format.length) {
    const match = TOKENS.find((t) => format.startsWith(t.token, fi));

    if (!match) {
      // Literal char in the format (separator). Advance the value one char to
      // stay aligned — whether it's a real separator or a masked format char.
      fi += 1;
      if (vi < value.length) {
        vi += 1;
      }
      continue;
    }

    // Consume the value's numeric run for this section.
    const start = vi;
    while (vi < value.length && /[0-9]/.test(value[vi])) {
      vi += 1;
    }
    // Placeholder/mask case: no digits here, so consume the token's width to
    // keep sections aligned and non-zero-width (e.g. value === "DD.MM.YYYY").
    if (vi === start) {
      vi = Math.min(start + match.token.length, value.length);
    }
    sections.push({ start, end: vi, unit: match.unit });
    fi += match.token.length;
  }

  return sections;
}

/** Returns the section whose range contains (or is nearest to) the caret. */
export function sectionAtCaret<T extends Section>(
  sections: T[],
  caret: number,
): T | null {
  if (sections.length === 0) {
    return null;
  }
  const hit = sections.find((s) => caret >= s.start && caret <= s.end);
  if (hit) {
    return hit;
  }
  // Caret past the end — step the last section.
  return sections[sections.length - 1];
}

/**
 * Sections for a two-endpoint range rendered as `start{separator}end`. Each
 * section is tagged with its `part`. Assumes both endpoints use `format` and
 * the separator does not itself contain digits.
 */
export function getRangeSections(
  format: string,
  separator: string,
  value: string,
): RangeSection[] {
  const sepIndex = value.indexOf(separator);
  const startText = sepIndex === -1 ? value : value.slice(0, sepIndex);
  const endText =
    sepIndex === -1 ? '' : value.slice(sepIndex + separator.length);
  const endOffset =
    sepIndex === -1 ? value.length : sepIndex + separator.length;

  const startSections: RangeSection[] = getSections(format, startText).map(
    (s) => ({ ...s, part: 'start' }),
  );
  const endSections: RangeSection[] = getSections(format, endText).map((s) => ({
    ...s,
    part: 'end',
    start: s.start + endOffset,
    end: s.end + endOffset,
  }));

  return [...startSections, ...endSections];
}

/** Steps `value` by `delta` (±1) on `unit`. dayjs handles wrap/clamp. */
export function stepValue(value: Dayjs, unit: Unit, delta: number): Dayjs {
  return value.add(delta, unit);
}
