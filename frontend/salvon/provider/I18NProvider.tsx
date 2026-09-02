import {
  type I18nextProviderProps,
  I18nextProvider as RI18NProvider,
} from 'react-i18next';

export default function I18NProvider(props: I18nextProviderProps) {
  return <RI18NProvider {...props} />;
}
