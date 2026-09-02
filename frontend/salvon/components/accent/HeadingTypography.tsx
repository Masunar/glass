import { Typography, type TypographyProps } from '@mui/material';

export default function HeadingTypography({
  children,
  sx,
  ...props
}: TypographyProps) {
  return (
    <Typography
      variant="h4"
      color="textPrimary"
      {...props}
      sx={{ fontWeight: 500, fontSize: '1.15rem', ...sx }}
    >
      {children}
    </Typography>
  );
}
