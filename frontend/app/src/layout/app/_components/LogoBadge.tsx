import { PiCellTower, PiCube } from 'react-icons/pi';

import { Div, Flex } from '@salvon/components/div';
import { usePalette } from '@salvon/hooks/useTheme';

type Props = {
  renderCompactMode: boolean;
};

export default function LogoBadge({ renderCompactMode }: Props) {
  const palette = usePalette();
  const name = 'Salvon Framework';
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
          fontWeight: 700,
          fontSize: '1.15rem',
          flexShrink: 0,
        }}
      >
        <PiCube />
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
