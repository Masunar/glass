import { LiaEdit } from 'react-icons/lia';

import { BaseButton, type BaseButtonProps } from '@salvon/components/button';
import { useTranslation } from '@salvon/hooks/useTranslation';

export default function EditButton({
  children,
  icon,
  ...props
}: BaseButtonProps) {
  const t = useTranslation();

  return (
    <BaseButton
      variant="contained"
      color="warning"
      icon={icon ?? <LiaEdit />}
      {...props}
    >
      {children ?? t('edit')}
    </BaseButton>
  );
}
