import { MdOutlineCancel } from 'react-icons/md';

import {
  BaseIconButton,
  type IconButtonProps,
} from '@salvon/components/icon-button';
import { useTranslation } from '@salvon/hooks/useTranslation';

export default function CancelIconButton({ icon, ...props }: IconButtonProps) {
  const t = useTranslation();

  return (
    <BaseIconButton
      color="secondary"
      icon={icon ?? <MdOutlineCancel />}
      label={t('cancel')}
      {...props}
    />
  );
}
