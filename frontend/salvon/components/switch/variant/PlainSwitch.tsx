import { Switch, type SwitchProps } from '@mui/material';
import { styled } from '@mui/material/styles';

const PlainSwitch = styled((props: SwitchProps) => (
  <Switch disableRipple {...props} />
))(({ theme }) => ({
  padding: 8,
  '& .MuiSwitch-track': {
    borderRadius: 22 / 2,
    '&:before, &:after': {
      content: '""',
      position: 'absolute',
      top: '50%',
      transform: 'translateY(-50%)',
      width: 16,
      height: 16,
    },
  },
  '& .MuiSwitch-thumb': {
    boxShadow: 'none',
    width: 18,
    height: 18,
    margin: 1,
    marginTop: 1,
    color: '#fff',
  },
  '& .MuiSwitch-switchBase': {
    background: 'none !important',
    transitionDuration: '270ms',
    margin: 0,
    '&.Mui-checked': {
      '& .MuiSwitch-thumb': {
        color: '#fff',
      },
      '& + .MuiSwitch-track': {
        backgroundColor: theme.palette.primary.main,
        opacity: 1,
      },
    },
    '&.Mui-disabled + .MuiSwitch-track': {
      cursor: 'not-allowed',
    },
  },
}));

export default PlainSwitch;
