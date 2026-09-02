import { type ReactNode, createContext, useEffect, useState } from 'react';

import { CountryApi } from '@app/api/CountryApi';

export type Country = {
  iso_3166_1_alpha2: string;
  display_name: string;
};

export const CountriesContext = createContext<Country[]>([]);

export type CountriesProviderProps = {
  children: ReactNode;
};

export default function CountriesProvider({ children }: CountriesProviderProps) {
  const [countries, setCountries] = useState<Country[]>([]);

  useEffect(() => {
    CountryApi.countries().then(({ content }) => {
      setCountries(content.data);
    });
  }, []);

  return (
    <CountriesContext.Provider value={countries}>
      {children}
    </CountriesContext.Provider>
  );
}
