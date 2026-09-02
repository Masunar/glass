import { Box } from '@mui/material';

import Cloud from './Cloud';
import Duck from './Duck';
import Flower from './Flower';
import Grass from './Grass';
import Meadow from './Meadow';
import Mountains from './Mountains';
import Plane from './Plane';
import Pond from './Pond';
import Sheep from './Sheep';
import Sun from './Sun';
import type { ReactNode } from 'react';

export default function Pasture({ planeBanner }: { planeBanner?: ReactNode }) {
  return (
    <Box
      sx={{
        position: 'relative',
        height: '100%',
        minHeight: '100%',
        overflow: 'hidden',
        borderRadius: 1,
        // warm glow spilling from the sun, over the plain sky gradient
        background:
          'radial-gradient(circle at 88% 10%, rgba(247,208,96,.18), rgba(247,208,96,.05) 32%, rgba(247,208,96,0) 55%), linear-gradient(#e6edf8, #eff2f4)',
      }}
    >
      <Sun top="7%" right="10%" />
      <Mountains />

      <Plane banner={planeBanner} top="24%" />

      <Cloud top="8%" left="14%" width="7rem" />
      <Cloud top="20%" left="76%" width="5rem" />

      <Meadow>
        <Pond bottom="8%" right="6%">
          <Duck size={24} top="18%" left="16%" swim="90px" duration={16} />
          <Duck size={16} top="52%" left="46%" swim="50px" duration={11} />
        </Pond>

        <Flower color="#f2c94c" top="42%" left="16%" />
        <Flower color="#fff" top="70%" left="42%" />
        <Flower color="#fff" size={9} top="30%" left="52%" />
        <Flower color="#f2c94c" size={8} top="78%" left="34%" />
        <Flower color="#e8a0b4" size={10} top="60%" left="62%" />
        <Flower color="#f2c94c" size={9} top="86%" left="8%" />

        <Grass top="52%" left="28%" />
        <Grass height={13} top="74%" left="46%" />
        <Grass height={22} top="88%" left="80%" />
        <Grass height={15} top="36%" left="6%" />
        <Grass height={16} top="88%" left="66%" />

        {/* top right corner of the meadow */}
        <Flower color="#fff" size={8} top="8%" left="84%" />
        <Flower color="#f2c94c" size={9} top="16%" left="72%" />
        <Flower color="#e8a0b4" size={8} top="4%" left="93%" />
        <Grass height={14} top="12%" left="90%" />
        <Grass height={17} top="6%" left="78%" />
        <Grass height={12} top="20%" left="96%" />

        {/* top left corner of the meadow */}
        <Flower color="#f2c94c" size={8} top="6%" left="4%" />
        <Flower color="#fff" size={9} top="14%" left="18%" />
        <Flower color="#e8a0b4" size={8} top="4%" left="26%" />
        <Grass height={16} top="10%" left="10%" />
        <Grass height={13} top="18%" left="2%" />
        <Grass height={15} top="6%" left="22%" />

        <Box sx={{ position: 'absolute', top: '-4%', left: '32%' }}>
          <Sheep scale={1} />
        </Box>
        <Box sx={{ position: 'absolute', top: '46%', left: '14%' }}>
          <Sheep scale={0.55} />
        </Box>
        <Box sx={{ position: 'absolute', top: '22%', right: '10%' }}>
          <Sheep scale={0.75} facing="left" />
        </Box>
      </Meadow>
    </Box>
  );
}
