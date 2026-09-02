import { Box } from '@mui/material';

import type { Pos } from './position';

type Props = Pos & {
  height?: number;
};

/* tuft of three leaves fanning out, middle one tallest */
export default function Grass({ height = 18, ...pos }: Props) {
  return (
    <Box
      sx={{
        position: 'absolute',
        display: 'flex',
        alignItems: 'flex-end',
        ...pos,
      }}
    >
      {[-22, 0, 20].map((angle, i) => (
        <Box
          key={angle}
          sx={{
            width: 3,
            height: i === 1 ? height : height * 0.75,
            margin: '0 1px',
            borderRadius: '2px 2px 0 0',
            background: '#a9c78e',
            transform: `rotate(${angle}deg)`,
            transformOrigin: 'bottom center',
          }}
        />
      ))}
    </Box>
  );
}
