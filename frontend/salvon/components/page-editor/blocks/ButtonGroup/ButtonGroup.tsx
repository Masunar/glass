import Button from '@mui/material/Button';

import { Div } from '@salvon/components/div';

type ButtonItem = {
  text: string;
  href: string;
  variant: 'outlined' | 'contained' | 'text';
};

type Props = {
  buttons: ButtonItem[];
  align: 'left' | 'center' | 'right';
  gap: number;
};

const justifyMap = {
  left: 'flex-start',
  center: 'center',
  right: 'flex-end',
};

export default function ButtonGroup({ buttons, align, gap }: Props) {
  return (
    <Div
      sx={{
        display: 'flex',
        flexWrap: 'wrap',
        justifyContent: justifyMap[align],
        gap: `${gap}px`,
      }}
    >
      {buttons.map((btn, i) => (
        <Button
          key={i}
          variant={btn.variant}
          color="primary"
          href={btn.href}
          sx={{ textTransform: 'none' }}
        >
          {btn.text}
        </Button>
      ))}
    </Div>
  );
}
