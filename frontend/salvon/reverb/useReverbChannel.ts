import { type ChannelVisibility, channelName } from './channel';
import { releaseChannel, subscribeChannel } from './client';
import { useEffect, useRef } from 'react';

import { isClient } from '@salvon/utils/ssr';

type ReverbEventCallback = (payload: any) => void;

export type UseReverbWebsocketOptions = {
  channel: string;
  event: string;
  callback: ReverbEventCallback;
  visibility?: ChannelVisibility;
  enabled?: boolean;
};

export const useReverbChannel = ({
  channel,
  event,
  callback,
  visibility = 'private',
  enabled = true,
}: UseReverbWebsocketOptions): void => {
  const callbackRef = useRef(callback);
  callbackRef.current = callback;

  useEffect(() => {
    if (!isClient() || !enabled || !channel) {
      return;
    }

    const name = channelName(channel, visibility);
    const pusherChannel = subscribeChannel(name);
    const handler: ReverbEventCallback = (payload) =>
      callbackRef.current(payload);

    pusherChannel.bind(event, handler);

    return () => {
      pusherChannel.unbind(event, handler);
      releaseChannel(name);
    };
  }, [channel, event, visibility, enabled]);
};
