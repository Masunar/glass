import { Toolbar, type ToolbarProps } from '@mui/material';

export type TopbarSizerProps = ToolbarProps & {
  height?: number | string;
};

export default function TopbarSizer({
  sx,
  children,
  height,
  ...props
}: TopbarSizerProps) {
  return (
    <Toolbar
      {...props}
      sx={{ height: height ?? '56px', background: 'inherit', ...sx }}
      disableGutters={true}
    >
      {children}
    </Toolbar>
  );
}
