import { Box, Typography } from '@mui/material';

import LogoMark from './LogoMark';

import { Flex } from '@salvon/components/div';
import { useTranslation } from '@salvon/hooks/useTranslation';

type Props = {
  /** Kolor znaku i nazwy. Domyślnie granat z palety motywu. */
  color?: string;
  size?: number;
};

/** Znak marki: logo plus nazwa i podpis, złożone w jeden blok. */
export default function Brand({ color = '#1b3358', size = 34 }: Props) {
  const t = useTranslation();

  return (
    <Flex align="center" gap={1.75}>
      <LogoMark size={size} color={color} title={t('brand_name')} />
      <Box>
        <Typography
          component="p"
          sx={{
            fontSize: '1.05rem',
            fontWeight: 600,
            letterSpacing: '.16em',
            lineHeight: 1.1,
            textTransform: 'uppercase',
            color,
          }}
        >
          {t('brand_name')}
        </Typography>
        <Typography
          component="p"
          sx={{
            mt: 0.75,
            fontSize: '.78rem',
            letterSpacing: '.04em',
            color: 'rgba(37,74,148,.72)',
          }}
        >
          {t('brand_tagline')}
        </Typography>
      </Box>
    </Flex>
  );
}
