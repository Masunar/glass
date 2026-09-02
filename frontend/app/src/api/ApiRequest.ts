import env from '@app_env';

import { AsyncApiRequest } from '@salvon/api';

export class ApiRequest extends AsyncApiRequest {
  protected static baseUrl = env.api_url + '/' + env.api_suffix;
}
