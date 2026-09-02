import { LinearProgress, type SxProps } from '@mui/material';

import { type ReactNode, useEffect, useState } from 'react';
import { useNavigation } from 'react-router';

import { Div } from '@salvon/components/div';

export default function NavigationIndicator({
  children,
  sx,
  height = '2px',
}: {
  children: ReactNode;
  height?: string | number;
  sx?: Omit<SxProps, 'height' | 'background'>;
}) {
  const [value, setValue] = useState(0);
  const [visible, setVisible] = useState(false);
  const navigation = useNavigation();
  const state = navigation.state;

  useEffect(() => {
    let interval: any;

    if (state === 'loading' || state === 'submitting') {
      setValue(10);
      setVisible(true);

      interval = setInterval(() => {
        setValue((cv) => {
          if (cv > 90) {
            return cv;
          }

          return cv + Math.random() * 10;
        });
      }, 300);
    } else {
      setValue(100);
      setTimeout(() => {
        setVisible(false);
      }, 300);
    }

    return () => {
      if (interval) {
        clearInterval(interval);
      }
    };
  }, [state]);

  return (
    <>
      <Div
        sx={{
          position: 'fixed',
          top: 0,
          zIndex: 10000000,
          width: '100%',
          display: visible ? 'normal' : 'none',
        }}
      >
        <LinearProgress
          value={value}
          variant="determinate"
          sx={{ ...sx, background: 'transparent', height }}
        />
      </Div>
      {children}
    </>
  );
}
