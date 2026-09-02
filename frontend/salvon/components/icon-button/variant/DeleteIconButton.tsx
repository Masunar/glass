import { BiTrash } from 'react-icons/bi';

import {
  BaseIconButton,
  type IconButtonProps,
} from '@salvon/components/icon-button';
import { useTranslation } from '@salvon/hooks/useTranslation';

export default function DeleteIconButton({ icon, ...props }: IconButtonProps) {
  const t = useTranslation();

  return (
    <BaseIconButton
      color="error"
      icon={icon ?? <BiTrash />}
      label={t('delete')}
      {...props}
    />
  );
}
