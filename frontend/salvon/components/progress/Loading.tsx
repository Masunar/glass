import {
  CircularProgress,
  type CircularProgressProps,
  LinearProgress,
  type LinearProgressProps,
} from '@mui/material';

type CircuralLoader = CircularProgressProps & {
  type?: 'circular';
};

type LinearLoader = LinearProgressProps & {
  type: 'linear';
};

export type LoadingProps = CircuralLoader | LinearLoader;

export default function Loading({ type = 'circular', ...props }: LoadingProps) {
  if (type === 'circular') {
    return <CircularProgress {...(props as CircularProgressProps)} />;
  }

  return <LinearProgress {...(props as LinearProgressProps)} />;
}
