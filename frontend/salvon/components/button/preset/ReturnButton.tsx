import { PiArrowArcLeft } from 'react-icons/pi';

import { BaseButton, type BaseButtonProps } from '@salvon/components/button';
import { useTranslation } from '@salvon/hooks/useTranslation';

export default function ReturnButton({
  children,
  icon,
  ...props
}: BaseButtonProps) {
  const t = useTranslation();

  return (
    <BaseButton icon={icon ?? <PiArrowArcLeft />} {...props}>
      {children ?? t('return')}
    </BaseButton>
  );
}
