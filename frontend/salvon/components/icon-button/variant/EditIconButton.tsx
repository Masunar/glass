import { LiaEdit } from 'react-icons/lia';

import {
  BaseIconButton,
  type IconButtonProps,
} from '@salvon/components/icon-button';
import { useTranslation } from '@salvon/hooks/useTranslation';

export default function EditIconButton({ icon, ...props }: IconButtonProps) {
  const t = useTranslation();

  return (
    <BaseIconButton
      color="warning"
      icon={icon ?? <LiaEdit />}
      label={t('edit')}
      {...props}
    />
  );
}
