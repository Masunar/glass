import { PiPlusCircle } from 'react-icons/pi';

import { BaseButton, type BaseButtonProps } from '@salvon/components/button';
import { useTranslation } from '@salvon/hooks/useTranslation';

export default function AddButton({
  children,
  icon,
  ...props
}: BaseButtonProps) {
  const t = useTranslation();

  return (
    <BaseButton
      variant="contained"
      color="success"
      icon={icon ?? <PiPlusCircle />}
      {...props}
    >
      {children ?? t('add')}
    </BaseButton>
  );
}
