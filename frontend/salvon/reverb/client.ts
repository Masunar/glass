import env from '@app_env';

import Pusher, { type Channel } from 'pusher-js';
import nacl from 'tweetnacl';

const IDLE_DISCONNECT_MS = 30_000;

let client: Pusher | undefined;
let idleTimer: ReturnType<typeof setTimeout> | undefined;
let visibilityBound = false;

const refCounts = new Map<string, number>();

const getXsrfToken = (): string | undefined => {
  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
  return match ? decodeURIComponent(match[1]) : undefined;
};

const authEndpoint = (): string =>
  `${env.api_url.replace(/\/$/, '')}/broadcasting/auth`;

const teardown = (): void => {
  clearTimeout(idleTimer);
  idleTimer = undefined;
  client?.disconnect();
  client = undefined;
};

const cancelIdleDisconnect = (): void => {
  clearTimeout(idleTimer);
  idleTimer = undefined;
};

const scheduleIdleDisconnect = (): void => {
  cancelIdleDisconnect();
  idleTimer = setTimeout(() => {
    if (refCounts.size === 0) {
      teardown();
    }
  }, IDLE_DISCONNECT_MS);
};

const bindVisibility = (): void => {
  if (visibilityBound || typeof document === 'undefined') {
    return;
  }
  visibilityBound = true;

  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden' && refCounts.size === 0) {
      teardown();
    }
  });
  window.addEventListener('pagehide', () => {
    if (refCounts.size === 0) {
      teardown();
    }
  });
};

export const getReverbClient = (): Pusher => {
  if (client) {
    return client;
  }

  const { key, host, port, enforceTls } = env.reverb;
  const wsPort = Number(port);

  client = new Pusher(key, {
    wsHost: host,
    wsPort,
    wssPort: wsPort,
    forceTLS: enforceTls,
    enabledTransports: ['ws', 'wss'],
    cluster: '',
    nacl,
    authorizer: (channel: { name: string }) => ({
      authorize: (
        socketId: string,
        callback: (error: Error | null, data: unknown) => void,
      ) => {
        const xsrf = getXsrfToken();

        fetch(authEndpoint(), {
          method: 'POST',
          credentials: 'include',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            ...(xsrf ? { 'X-XSRF-TOKEN': xsrf } : {}),
          },
          body: JSON.stringify({
            socket_id: socketId,
            channel_name: channel.name,
          }),
        })
          .then(async (res) => {
            if (!res.ok)
              throw new Error(`Broadcast auth failed: ${res.status}`);
            callback(null, await res.json());
          })
          .catch((error) => callback(error, null));
      },
    }),
  } as any);

  bindVisibility();

  return client;
};

export const subscribeChannel = (name: string): Channel => {
  cancelIdleDisconnect();
  refCounts.set(name, (refCounts.get(name) ?? 0) + 1);
  return getReverbClient().subscribe(name);
};

export const releaseChannel = (name: string): void => {
  const next = (refCounts.get(name) ?? 1) - 1;
  if (next <= 0) {
    refCounts.delete(name);
    client?.unsubscribe(name);
    if (refCounts.size === 0) {
      scheduleIdleDisconnect();
    }
    return;
  }
  refCounts.set(name, next);
};
