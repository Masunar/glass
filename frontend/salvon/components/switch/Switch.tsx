import {
  Switch as MUISwitch,
  type SwitchProps as MUISwitchProps,
} from '@mui/material';

import IndicatedSwitch from './variant/IndicatedSwitch';
import MonoColorSwitch from './variant/MonoColorSwitch';
import PlainSwitch from './variant/PlainSwitch';

export type SwitchVariant = 'mono-color' | 'mui' | 'indicated' | 'plain';

export type SwitchProps = MUISwitchProps & {
  variant?: SwitchVariant;
};

export default function Switch({ variant, ...props }: SwitchProps) {
  if (variant === 'mui') {
    return <MUISwitch {...props} />;
  }

  if (variant === 'indicated') {
    return <IndicatedSwitch {...props} />;
  }

  if (variant === 'plain') {
    return <PlainSwitch {...props} />;
  }

  return <MonoColorSwitch {...props} />;
}
