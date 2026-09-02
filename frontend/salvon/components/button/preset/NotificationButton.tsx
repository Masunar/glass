import { BaseButton, type BaseButtonProps } from '@salvon/components/button';

/** Ultra-compact button for use inside notification footers. */
export default function NotificationButton({
  children,
  sx,
  ...props
}: BaseButtonProps) {
  return (
    <BaseButton
      size="small"
      {...props}
      sx={{
        minWidth: 0,
        padding: '5px 8px',
        fontSize: '0.75rem',
        fontWeight: 700,
        lineHeight: 1.2,
        borderRadius: '6px',
        '& .MuiButton-startIcon': {
          marginRight: '4px',
          '& > *': { fontSize: '0.85rem' },
        },
        ...sx,
      }}
    >
      {children}
    </BaseButton>
  );
}
