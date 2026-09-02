import { PiUserCirclePlus } from 'react-icons/pi';

import { BaseButton, type BaseButtonProps } from '@salvon/components/button';
import { useTranslation } from '@salvon/hooks/useTranslation';

export default function CreateButton({
  children,
  icon,
  ...props
}: BaseButtonProps) {
  const t = useTranslation();

  return (
    <BaseButton
      variant="contained"
      color="success"
      icon={icon ?? <PiUserCirclePlus />}
      {...props}
    >
      {children ?? t('create')}
    </BaseButton>
  );
}
