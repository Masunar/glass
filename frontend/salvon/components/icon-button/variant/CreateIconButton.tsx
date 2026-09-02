import { PiUserCirclePlus } from 'react-icons/pi';

import {
  BaseIconButton,
  type IconButtonProps,
} from '@salvon/components/icon-button';
import { useTranslation } from '@salvon/hooks/useTranslation';

export default function CreateIconButton({ icon, ...props }: IconButtonProps) {
  const t = useTranslation();

  return (
    <BaseIconButton
      color="success"
      icon={icon ?? <PiUserCirclePlus />}
      label={t('create')}
      {...props}
    />
  );
}
