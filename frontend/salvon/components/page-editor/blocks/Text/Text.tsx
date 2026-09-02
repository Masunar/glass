import Typography from '@mui/material/Typography';

type Align = 'left' | 'center' | 'right';
type Size = 'sm' | 'md' | 'lg';

const sizeMap: Record<Size, string> = {
  sm: '13px',
  md: '16px',
  lg: '20px',
};

type Props = {
  text: string;
  align: Align;
  size: Size;
};

export default function Text({ text, align, size }: Props) {
  return (
    <Typography
      component="p"
      sx={{
        fontSize: sizeMap[size],
        textAlign: align,
        lineHeight: 1.7,
        color: 'inherit',
        whiteSpace: 'pre-wrap',
      }}
    >
      {text}
    </Typography>
  );
}
