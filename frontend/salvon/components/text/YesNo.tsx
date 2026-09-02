import { useTranslation } from '@salvon/hooks/useTranslation';
import { boolVal } from '@salvon/utils/type-transform';

export default function YesNo({ decisiveValue }: { decisiveValue: any }) {
  const t = useTranslation();

  return boolVal(decisiveValue) ? t('yes') : t('no');
}
