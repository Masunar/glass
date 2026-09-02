import { MdCancel } from 'react-icons/md';

import { BaseButton, type BaseButtonProps } from '@salvon/components/button';
import { useTranslation } from '@salvon/hooks/useTranslation';

export default function CancelButton({
  children,
  icon,
  ...props
}: BaseButtonProps) {
  const t = useTranslation();

  return (
    <BaseButton
      variant="contained"
      color="secondary"
      icon={icon ?? <MdCancel />}
      {...props}
    >
      {children ?? t('cancel')}
    </BaseButton>
  );
}
