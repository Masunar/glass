import { BiTrash } from 'react-icons/bi';

import { BaseButton, type BaseButtonProps } from '@salvon/components/button';
import { useTranslation } from '@salvon/hooks/useTranslation';

export default function DeleteButton({
  children,
  icon,
  ...props
}: BaseButtonProps) {
  const t = useTranslation();

  return (
    <BaseButton
      variant="contained"
      color="error"
      icon={icon ?? <BiTrash />}
      {...props}
    >
      {children ?? t('delete')}
    </BaseButton>
  );
}
