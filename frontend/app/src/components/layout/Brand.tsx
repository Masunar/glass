import { Box, Typography } from '@mui/material';

import { Flex } from '@salvon/components/div';
import { useTranslation } from '@salvon/hooks/useTranslation';

/**
 * Znak marki składany typografią. Sygnet to tafla szkła: kwadrat
 * z rozświetloną krawędzią górną i zielonkawą dolną, tak jak tafle
 * w scenie na panelu logowania.
 */
export default function Brand() {
  const t = useTranslation();

  return (
    <Flex align="center" gap={1.75}>
      <Box
        aria-hidden
        sx={{
          position: 'relative',
          width: 34,
          height: 34,
          flexShrink: 0,
          borderRadius: '4px',
          transform: 'rotate(-6deg)',
          background:
            'linear-gradient(135deg, rgba(255,255,255,.9) 0%, rgba(215,235,238,.55) 50%, rgba(255,255,255,.8) 100%)',
          boxShadow:
            'inset 1px 1px 0 rgba(255,255,255,.95), inset -1.5px -1.5px 0 rgba(43,110,99,.4), 0 6px 14px -6px rgba(23,48,77,.45)',
          '&::before': {
            content: '""',
            position: 'absolute',
            inset: 0,
            borderRadius: 'inherit',
            background:
              'linear-gradient(114deg, transparent 30%, rgba(255,255,255,.85) 46%, transparent 62%)',
          },
        }}
      />
      <Box>
        <Typography
          component="p"
          sx={{
            fontSize: '1.05rem',
            fontWeight: 600,
            letterSpacing: '.16em',
            lineHeight: 1.1,
            textTransform: 'uppercase',
            color: '#1b3358',
          }}
        >
          Glass Expert
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
