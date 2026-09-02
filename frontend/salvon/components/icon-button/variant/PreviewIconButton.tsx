import { PiEye } from 'react-icons/pi';

import {
  BaseIconButton,
  type IconButtonProps,
} from '@salvon/components/icon-button';
import { useTranslation } from '@salvon/hooks/useTranslation';

export default function PreviewIconButton({ icon, ...props }: IconButtonProps) {
  const t = useTranslation();

  return (
    <BaseIconButton
      color="info"
      icon={icon ?? <PiEye />}
      label={t('preview')}
      {...props}
    />
  );
}
