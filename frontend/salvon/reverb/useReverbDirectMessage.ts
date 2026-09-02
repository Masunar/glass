import { type ChannelVisibility, channelName } from './channel';
import { releaseChannel, subscribeChannel } from './client';
import { useCallback, useEffect, useRef } from 'react';

import { isClient } from '@salvon/utils/ssr';

type DirectMessageCallback = (payload: any) => void;

export type UseReverbDirectMessageOptions = {
  channel: string;
  event: string;
  callback?: DirectMessageCallback;
  visibility?: Extract<ChannelVisibility, 'private' | 'encrypted' | 'presence'>;
  enabled?: boolean;
};

export const useReverbDirectMessage = ({
  channel,
  event,
  callback,
  visibility = 'private',
  enabled = true,
}: UseReverbDirectMessageOptions): ((payload: any) => void) => {
  const callbackRef = useRef(callback);
  callbackRef.current = callback;
  const channelRef = useRef<ReturnType<typeof subscribeChannel> | null>(null);

  useEffect(() => {
    if (!isClient() || !enabled || !channel) {
      return;
    }

    const name = channelName(channel, visibility);
    const pusherChannel = subscribeChannel(name);
    channelRef.current = pusherChannel;

    const clientEvent = `client-${event}`;
    const handler: DirectMessageCallback = (payload) =>
      callbackRef.current?.(payload);

    pusherChannel.bind(clientEvent, handler);

    return () => {
      pusherChannel.unbind(clientEvent, handler);
      releaseChannel(name);
      channelRef.current = null;
    };
  }, [channel, event, visibility, enabled]);

  return useCallback(
    (payload: any) => {
      channelRef.current?.trigger(`client-${event}`, payload);
    },
    [event],
  );
};
