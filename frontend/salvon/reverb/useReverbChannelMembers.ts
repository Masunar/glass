import { channelName } from './channel';
import { releaseChannel, subscribeChannel } from './client';
import { useEffect, useRef } from 'react';

import { isClient } from '@salvon/utils/ssr';

export type ChannelMember = {
  id: string | number;
  info: any;
};

export type UseReverbChannelMembersOptions = {
  channel: string;
  members?: (members: ChannelMember[]) => void;
  onJoin?: (member: ChannelMember) => void;
  onLeave?: (member: ChannelMember) => void;
  enabled?: boolean;
};

const membersToArray = (data: {
  members?: Record<string, any>;
}): ChannelMember[] =>
  Object.entries(data.members ?? {}).map(([id, info]) => ({ id, info }));

export const useReverbChannelMembers = ({
  channel,
  members,
  onJoin,
  onLeave,
  enabled = true,
}: UseReverbChannelMembersOptions): void => {
  const refs = useRef({ members, onJoin, onLeave });
  refs.current = { members, onJoin, onLeave };

  useEffect(() => {
    if (!isClient() || !enabled || !channel) {
      return;
    }

    const name = channelName(channel, 'presence');
    const pusherChannel = subscribeChannel(name);

    const onSubscribed = (data: { members?: Record<string, any> }) =>
      refs.current.members?.(membersToArray(data));
    const onAdded = (member: ChannelMember) => refs.current.onJoin?.(member);
    const onRemoved = (member: ChannelMember) => refs.current.onLeave?.(member);

    pusherChannel.bind('pusher:subscription_succeeded', onSubscribed);
    pusherChannel.bind('pusher:member_added', onAdded);
    pusherChannel.bind('pusher:member_removed', onRemoved);

    return () => {
      pusherChannel.unbind('pusher:subscription_succeeded', onSubscribed);
      pusherChannel.unbind('pusher:member_added', onAdded);
      pusherChannel.unbind('pusher:member_removed', onRemoved);
      releaseChannel(name);
    };
  }, [channel, enabled]);
};
