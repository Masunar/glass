import { PiFloppyDisk } from 'react-icons/pi';

import {
  BaseIconButton,
  type IconButtonProps,
} from '@salvon/components/icon-button';
import { useTranslation } from '@salvon/hooks/useTranslation';

export default function SaveIconButton({ icon, ...props }: IconButtonProps) {
  const t = useTranslation();

  return (
    <BaseIconButton
      color="success"
      icon={icon ?? <PiFloppyDisk />}
      label={t('save')}
      {...props}
    />
  );
}
