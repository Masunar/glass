import { ApiRequest } from '@app/api/ApiRequest';

export class CountryApi extends ApiRequest {
  static async countries() {
    return this.get(`/country`);
  }

  static async country(iso: string) {
    return this.get(`/country/${iso}`);
  }
}
