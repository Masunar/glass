import { PiSignIn } from 'react-icons/pi';

import { BaseButton, type BaseButtonProps } from '@salvon/components/button';
import { useTranslation } from '@salvon/hooks/useTranslation';

export default function OpenButton({
  children,
  icon,
  ...props
}: BaseButtonProps) {
  const t = useTranslation();

  return (
    <BaseButton
      variant="contained"
      color="info"
      icon={icon ?? <PiSignIn />}
      {...props}
    >
      {children ?? t('open')}
    </BaseButton>
  );
}
