import { drawer, industry, modules, rail, spotlight } from './tokens';

/**
 * Zmienne CSS wyprowadzone z rejestru tokenów.
 *
 * Dwa światy potrzebują tych samych kolorów w innej postaci: paleta MUI
 * chce obiektu JavaScript, a warstwa list i nawigacji — zmiennych CSS.
 * Zamiast utrzymywać dwie listy, ktore po miesiacu sie rozjada, druga
 * jest generowana z pierwszej.
 */
export function buildCssVariables(): string {
  const lines: string[] = [
    `--ge-bg: ${industry.bg};`,
    `--ge-surface: ${industry.surface};`,
    `--ge-text: ${industry.text};`,
    `--ge-divider: ${industry.divider};`,
    `--ge-accent: ${industry.accent.base};`,
    `--ge-font-heading: ${industry.font.heading};`,
    `--ge-font-body: ${industry.font.body};`,
    `--ge-shadow-md: ${industry.shadow.md};`,
    `--ge-rail-bg: ${rail.bg};`,
    `--ge-rail-fg: ${rail.fg};`,
    `--ge-rail-tile: ${rail.tile};`,
    `--ge-rail-brand-bg: ${rail.brandBg};`,
    `--ge-rail-brand-fg: ${rail.brandFg};`,
    `--ge-drawer-head-bg: ${drawer.headBg};`,
    `--ge-drawer-head-fg: ${drawer.headFg};`,
    `--ge-drawer-head-kicker: ${drawer.headKicker};`,
    `--ge-drawer-switch-bg: ${drawer.switchBg};`,
    `--ge-drawer-switch-fg: ${drawer.switchFg};`,
    `--ge-drawer-shadow: ${drawer.shadow};`,
    `--ge-scrim: ${drawer.scrim};`,
    `--ge-spot-bg: ${spotlight.bg};`,
    `--ge-spot-fg: ${spotlight.fg};`,
    `--ge-spot-dim: ${spotlight.dim};`,
    `--ge-spot-line: ${spotlight.line};`,
    `--ge-spot-hover: ${spotlight.hover};`,
    `--ge-spot-accent: ${spotlight.accent};`,
    `--ge-spot-shadow: ${spotlight.shadow};`,
  ];

  for (const [step, value] of Object.entries(industry.accent)) {
    if (step !== 'base') {
      lines.push(`--ge-accent-${step}: ${value};`);
    }
  }

  for (const [step, value] of Object.entries(industry.neutral)) {
    lines.push(`--ge-neutral-${step}: ${value};`);
  }

  for (const [key, hue] of Object.entries(modules)) {
    lines.push(`--m-${key}: ${hue.base};`);
    lines.push(`--m-${key}-tint: ${hue.tint};`);
  }

  return `:root{${lines.join('')}}`;
}
