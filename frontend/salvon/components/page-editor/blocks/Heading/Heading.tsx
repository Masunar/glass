import Typography from '@mui/material/Typography';

type Level = 'h1' | 'h2' | 'h3' | 'h4' | 'h5' | 'h6';
type Align = 'left' | 'center' | 'right';
type Size = 'xs' | 'sm' | 'md' | 'lg' | 'xl';

const sizeMap: Record<Size, string> = {
  xs: '16px',
  sm: '20px',
  md: '28px',
  lg: '36px',
  xl: '48px',
};

type Props = {
  text: string;
  level: Level;
  align: Align;
  size: Size;
  color: 'default' | 'accent';
};

export default function Heading({ text, level, align, size, color }: Props) {
  return (
    <Typography
      component={level}
      variant={level}
      sx={{
        fontSize: sizeMap[size],
        textAlign: align,
        color: color === 'accent' ? 'primary.main' : 'inherit',
        lineHeight: 1.4,
      }}
    >
      {text}
    </Typography>
  );
}
