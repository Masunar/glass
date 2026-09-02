import { isClient } from '@salvon/utils/ssr';

export default {
  dev: true,
  api_url: isClient()
    ? 'http://localhost:8080'
    : 'http://host.docker.internal:8080',
  api_suffix: 'api',
  reverb: {
    key: 'salvon_key',
    host: 'localhost',
    port: '8082',
    enforceTls: false,
  },
};
