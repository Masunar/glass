import { ApiRequest } from '@app/api/ApiRequest';

export class RegonApi extends ApiRequest {
  static async findByNip(nip: string) {
    return this.post(`/regon/find-by-nip`, { nip });
  }
}
