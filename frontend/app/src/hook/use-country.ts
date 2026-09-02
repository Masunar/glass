import { use, useMemo } from 'react';

import { CountriesContext, type Country } from '@app/provider/CountriesProvider';

export const useCountries = (): Country[] => use(CountriesContext);

export const useCountryName = (): ((code?: string | null) => string) => {
  const countries = useCountries();

  return useMemo(() => {
    const byCode = new Map(
      countries.map((c) => [
        c.iso_3166_1_alpha2?.toLowerCase(),
        c.display_name,
      ]),
    );

    return (code?: string | null): string => {
      if (!code) return '';
      return byCode.get(code.toLowerCase()) ?? code;
    };
  }, [countries]);
};
