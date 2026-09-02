import { PiCopy } from 'react-icons/pi';

import { BaseButton, type BaseButtonProps } from '@salvon/components/button';
import { useTranslation } from '@salvon/hooks/useTranslation';

export default function CopyButton({
  children,
  icon,
  ...props
}: BaseButtonProps) {
  const t = useTranslation();

  return (
    <BaseButton
      variant="contained"
      color="info"
      icon={icon ?? <PiCopy />}
      {...props}
    >
      {children ?? t('copy')}
    </BaseButton>
  );
}
