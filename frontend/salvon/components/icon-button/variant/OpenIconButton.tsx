import { PiSignIn } from 'react-icons/pi';

import {
  BaseIconButton,
  type IconButtonProps,
} from '@salvon/components/icon-button';
import { useTranslation } from '@salvon/hooks/useTranslation';

export default function OpenIconButton({ icon, ...props }: IconButtonProps) {
  const t = useTranslation();

  return (
    <BaseIconButton
      color="info"
      icon={icon ?? <PiSignIn />}
      label={t('open')}
      {...props}
    />
  );
}
