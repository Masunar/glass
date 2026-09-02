import { Div } from '@salvon/components/div';

type Props = {
  thickness: number;
  color: 'default' | 'accent';
  spacing: number;
};

export default function Separator({ thickness, color, spacing }: Props) {
  return (
    <Div
      fw
      sx={{
        my: `${spacing ?? 16}px`,
        borderBottomWidth: `${thickness ?? 1}px`,
        borderBottomStyle: 'solid',
        borderColor: color === 'accent' ? 'primary.main' : 'divider',
      }}
    />
  );
}
