import { Div } from '@salvon/components/div';

type Props = {
  height: string;
};

export default function Space({ height }: Props) {
  return (
    <Div
      fw
      sx={{
        height: height,
      }}
    />
  );
}
