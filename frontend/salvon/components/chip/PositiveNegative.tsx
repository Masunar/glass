import { Chip, type ChipProps } from '@mui/material';

import { useTranslation } from '@salvon/hooks/useTranslation';

export type PositiveNegativeProps = {
  condition: boolean;
  extendLabel?: boolean;
  positiveTranslationKey?: string;
  negativeTranslationKey?: string;
} & ChipProps;

export default function PositiveNegative({
  condition,
  sx,
  extendLabel = true,
  variant = 'filled',
  positiveTranslationKey = 'yes',
  negativeTranslationKey = 'no',
}: PositiveNegativeProps) {
  const t = useTranslation();

  const label = condition
    ? t(positiveTranslationKey)
    : t(negativeTranslationKey);
  const color: any = condition ? 'success' : 'error';

  return (
    <Chip
      size="small"
      label={label}
      variant={variant}
      color={color}
      sx={{
        ...(extendLabel && {
          '& .MuiChip-label': {
            padding: '0 13px',
            fontSize: '12px',
          },
        }),
        ...sx,
      }}
    />
  );
}
