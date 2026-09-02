import Slider from '@mui/material/Slider';
import { styled } from '@mui/material/styles';

const StyledSlider = styled(Slider)(({ theme }) => ({
  height: 6,
  padding: '13px 0',
  '& .MuiSlider-rail': {
    opacity: 1,
    backgroundColor: theme.palette.mode === 'dark' ? '#303030' : '#e6eaf0',
    borderRadius: 999,
  },
  '& .MuiSlider-track': {
    border: 'none',
    borderRadius: 999,
  },
  '& .MuiSlider-thumb': {
    height: 18,
    width: 18,
    backgroundColor: theme.palette.mode === 'dark' ? '#181818' : '#ffffff',
    border: `2px solid ${theme.palette.primary.main}`,
    boxShadow:
      theme.palette.mode === 'dark'
        ? '0 1px 3px rgba(0,0,0,0.5)'
        : '0 1px 3px rgba(15,23,42,0.2)',
    transition: 'box-shadow 0.15s ease',
    '&:focus, &:hover, &.Mui-active, &.Mui-focusVisible': {
      boxShadow: `0 0 0 6px ${theme.palette.primary.main}24`,
    },
    '&::before': {
      display: 'none',
    },
  },
  '& .MuiSlider-valueLabel': {
    lineHeight: 1,
    fontSize: 11,
    background: 'unset',
    padding: 0,
    width: 26,
    height: 26,
    borderRadius: '100% 100% 100% 30%',
    backgroundColor: '#000',
    transformOrigin: 'bottom left',
    transitionDuration: '150ms',
    transform: 'translate(50%, -100%) rotate(-45deg) scale(0)',
    '&::before': { display: 'none' },
    '&.MuiSlider-valueLabelOpen': {
      transform: 'translate(50%, -100%) rotate(-45deg) scale(1)',
    },
    '& > *': {
      transform: 'rotate(45deg)',
    },
  },
}));

export default StyledSlider;
