import { useTranslation as useI18nTranslation } from 'react-i18next';

export const useTranslation = (ns: string | undefined = undefined) => {
  const { t } = useI18nTranslation(ns);

  return t;
};

export const useI18N = () => {
  const { i18n } = useI18nTranslation();

  return i18n;
};
