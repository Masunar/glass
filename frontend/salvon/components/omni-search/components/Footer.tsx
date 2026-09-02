import type { OmniSearchFooterLabels } from '../types.d';
import Legend from './Legend';

import { Div, Flex } from '@salvon/components/div';
import { useIsDarkMode, usePalette } from '@salvon/hooks/useTheme';

export type FooterProps = {
  labels?: OmniSearchFooterLabels;
  count: number;
};

export default function Footer({ labels, count }: FooterProps) {
  const palette = usePalette();
  const dark = useIsDarkMode();
  const cfg = palette.salvon?.omni_search ?? {};
  const muted = palette.text?.secondary ?? (dark ? '#8a8f98' : '#94a3b8');

  return (
    <Flex
      aCenter
      jBetween
      sx={{
        px: 2,
        height: 44,
        fontSize: '0.78rem',
        color: muted,
        borderTop: `1px solid ${cfg.border ?? (dark ? '#2c2c2f' : '#eef0f3')}`,
        background: cfg.footerBg ?? (dark ? '#0c0c0e' : '#fafbfc'),
      }}
    >
      <Flex aCenter gap={2}>
        <Legend keys="↑↓" label={labels?.navigation} />
        <Legend keys="↵" label={labels?.open} />
        <Legend keys="Esc" label={labels?.close} />
      </Flex>
      <Div>
        {count} {labels?.results}
      </Div>
    </Flex>
  );
}
