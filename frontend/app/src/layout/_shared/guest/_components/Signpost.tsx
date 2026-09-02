import { Box } from '@mui/material';

type Props = {
  scale?: number; // 1 = default size, everything inside is em-based
  onClick?: () => void;
};

const wood = '#a87c53';
const woodDark = '#8c6a52';

/* crooked wooden signpost — the arrows point nowhere, which is rather the point */
export default function Signpost({ scale = 1, onClick }: Props) {
  return (
    <Box
      onClick={onClick}
      title={onClick ? 'Bored? Give it a click' : undefined}
      sx={{
        position: 'relative',
        width: '7em',
        height: '8em',
        fontSize: `${scale}em`,
        cursor: onClick ? 'pointer' : 'default',
        // arrows sit still until you hover, then sweep round to point the other way
        '.plank': {
          transition: 'transform .8s cubic-bezier(.4, 1.4, .5, 1)',
        },
        '&:hover .plank': {
          transform: 'rotate(180deg)',
        },
        '&:hover .plank:last-of-type': {
          transitionDelay: '.15s',
        },
      }}
    >
      {/* post */}
      <Box
        sx={{
          position: 'absolute',
          bottom: '.6em',
          left: '3.2em',
          width: '.6em',
          height: '7em',
          borderRadius: '.3em .3em 0 0',
          background: woodDark,
        }}
      />

      {/* upper plank, pointing right */}
      <Box
        className="plank"
        sx={{
          position: 'absolute',
          top: '1.2em',
          left: '1.2em',
          width: '4.6em',
          height: '1.4em',
          background: wood,
          clipPath: 'polygon(0 0, 78% 0, 100% 50%, 78% 100%, 0 100%)',
          transformOrigin: '50% 50%', // the post itself
        }}
      />
      {/* lower plank, pointing left */}
      <Box
        className="plank"
        sx={{
          position: 'absolute',
          top: '3.1em',
          left: '1.6em',
          width: '4em',
          height: '1.2em',
          background: woodDark,
          clipPath: 'polygon(22% 0, 100% 0, 100% 100%, 22% 100%, 0 50%)',
          transformOrigin: '47.5% 50%', // the post itself
        }}
      />

      {/* tuft at the foot of the post */}
      <Box
        sx={{
          position: 'absolute',
          bottom: '.4em',
          left: '2.4em',
          display: 'flex',
          alignItems: 'flex-end',
        }}
      >
        {[-22, 0, 20].map((angle, i) => (
          <Box
            key={angle}
            sx={{
              width: '.18em',
              height: i === 1 ? '1.1em' : '.8em',
              margin: '0 .06em',
              borderRadius: '.1em .1em 0 0',
              background: '#a9c78e',
              transform: `rotate(${angle}deg)`,
              transformOrigin: 'bottom center',
            }}
          />
        ))}
      </Box>

      {/* ground shadow */}
      <Box
        sx={{
          position: 'absolute',
          bottom: '.1em',
          left: '1.8em',
          width: '3.4em',
          height: '.6em',
          borderRadius: '50%',
          background: 'rgba(0,0,0,.15)',
        }}
      />
    </Box>
  );
}
