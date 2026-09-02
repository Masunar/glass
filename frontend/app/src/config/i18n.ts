import pagePL from '../locale/pl/page.json';
import sharedPL from '../locale/pl/shared.json';
import validationPL from '../locale/pl/validation.json';
import { defaultLocale, getStoredLocale } from './locales';
import i18next from 'i18next';

const pl = {
  translation: { ...sharedPL, page: pagePL, validation: validationPL },
};

i18next
  .init({
    lng: getStoredLocale(),
    fallbackLng: defaultLocale,
    resources: {
      pl,
    },
  })
  .finally();

export default i18next;
