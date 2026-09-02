import { Div } from '@salvon/components/div';

export type NotificationAccentProps = {
  color?: string;
};

export default function NotificationAccent({ color }: NotificationAccentProps) {
  return (
    <Div
      sx={{
        flex: '0 0 4px',
        alignSelf: 'stretch',
        borderRadius: '999px',
        backgroundColor: color ?? 'primary.main',
      }}
    />
  );
}
