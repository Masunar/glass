import { Div, Flex } from '@salvon/components/div';
import { usePalette } from '@salvon/hooks/useTheme';
import { useTranslation } from '@salvon/hooks/useTranslation';

import LogoMark from '@app/components/layout/LogoMark';

type Props = {
  renderCompactMode: boolean;
};

export default function LogoBadge({ renderCompactMode }: Props) {
  const palette = usePalette();
  const t = useTranslation();
  const name = t('brand_name');

  return (
    <Flex
      aCenter
      justify={renderCompactMode ? 'center' : 'normal'}
      sx={{
        gap: '12px',
        minWidth: 0,
        mt: '2px',
        ml: renderCompactMode ? '0px' : '3px',
      }}
      fw
    >
      <Flex
        center
        sx={{
          width: 38,
          height: 38,
          borderRadius: '10px',
          //@ts-ignore
          background: palette.secondary?.main,
          color: '#fff',
          flexShrink: 0,
        }}
      >
        <LogoMark size={19} color="#fff" title={name} />
      </Flex>
      {!renderCompactMode && (
        <Div
          sx={{
            fontWeight: 500,
            fontSize: '1.15rem',
            color: 'text.primary',
            minWidth: 160,
            overflow: 'hidden',
          }}
        >
          {name}
        </Div>
      )}
    </Flex>
  );
}
