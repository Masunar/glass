import { Box } from '@mui/material';

type PeakProps = {
  left: string;
  width: string;
  height: string;
  color: string;
  snow?: boolean;
};

/* single peak: triangle with an optional snow cap */
function Peak({ left, width, height, color, snow }: PeakProps) {
  return (
    <Box
      sx={{
        position: 'absolute',
        bottom: 0,
        left,
        width,
        height,
        background: color,
        clipPath: 'polygon(50% 0, 100% 100%, 0 100%)',
      }}
    >
      {snow && (
        <Box
          sx={{
            position: 'absolute',
            top: 0,
            left: '25%',
            width: '50%',
            height: '34%',
            background: '#fff',
            opacity: 0.9,
            clipPath:
              'polygon(50% 0, 78% 62%, 64% 48%, 50% 74%, 36% 46%, 22% 62%)',
          }}
        />
      )}
    </Box>
  );
}

/* slab filling the valleys, so neighbouring peaks meet in a soft foothill instead of a sharp V */
function Base({ height, color }: { height: string; color: string }) {
  return (
    <Box
      sx={{
        position: 'absolute',
        bottom: 0,
        left: 0,
        right: 0,
        height,
        background: color,
        borderRadius: '40% 40% 0 0 / 18% 18% 0 0',
      }}
    />
  );
}

/* hazy range far back, a bigger snowy one in front of it */
export default function Mountains({ bottom = '38%' }: { bottom?: string }) {
  return (
    <Box
      sx={{ position: 'absolute', bottom, left: 0, right: 0, height: '26%' }}
    >
      <Base height="26%" color="#c2d2e2" />
      <Peak left="-6%" width="46%" height="62%" color="#c2d2e2" />
      <Peak left="28%" width="40%" height="52%" color="#c2d2e2" />
      <Peak left="62%" width="50%" height="66%" color="#c2d2e2" snow />

      <Base height="20%" color="#a9c0d4" />
      <Peak left="4%" width="42%" height="82%" color="#a9c0d4" snow />
      <Peak left="40%" width="52%" height="100%" color="#a9c0d4" snow />
      <Peak left="76%" width="44%" height="72%" color="#a9c0d4" />
    </Box>
  );
}
