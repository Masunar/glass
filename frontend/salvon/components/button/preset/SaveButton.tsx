import { PiFloppyDisk } from 'react-icons/pi';

import { BaseButton, type BaseButtonProps } from '@salvon/components/button';
import { useTranslation } from '@salvon/hooks/useTranslation';

export default function SaveButton({
  children,
  icon,
  ...props
}: BaseButtonProps) {
  const t = useTranslation();

  return (
    <BaseButton
      variant="contained"
      color="success"
      icon={icon ?? <PiFloppyDisk />}
      {...props}
    >
      {children ?? t('save')}
    </BaseButton>
  );
}
