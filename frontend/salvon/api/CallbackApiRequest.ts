import {
  CallbackRequest,
  type ResponseCallback,
  type ResponseContent,
} from '@salvon/request';
import type { Noop } from '@salvon/types';

export default class CallbackApiRequest extends CallbackRequest {
  protected static withCredentials: boolean = true;
  protected static withXSRFToken: boolean = true;
  protected static throwNonResponseErrors: boolean = false;

  public static async create(
    data: any,
    callback?: ResponseCallback<ResponseContent>,
    always: Noop = () => {},
  ) {
    await this.post('', data, callback).finally(always);
  }

  public static async read(
    id: number,
    callback?: ResponseCallback<ResponseContent>,
    always: Noop = () => {},
  ) {
    await this.get(`/${id}`, {}, callback).finally(always);
  }

  public static async update(
    id: number,
    data: any,
    callback?: ResponseCallback<ResponseContent>,
    always: Noop = () => {},
  ) {
    await this.put(`/${id}`, data, callback).finally(always);
  }

  public static async remove(
    id: number,
    callback?: ResponseCallback<ResponseContent>,
    always: Noop = () => {},
  ) {
    await this.delete(`/${id}`, {}, callback).finally(always);
  }

  public static async list(
    data: any,
    callback?: ResponseCallback<ResponseContent>,
    always: Noop = () => {},
  ) {
    await this.get('', data, callback).finally(always);
  }
}
