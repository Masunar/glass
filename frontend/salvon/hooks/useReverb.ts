import { useReverbChannel } from '@salvon/reverb/useReverbChannel';
import { useReverbChannelMembers } from '@salvon/reverb/useReverbChannelMembers';
import { useReverbDirectMessage } from '@salvon/reverb/useReverbDirectMessage';

export const useReverb = () => ({
  subscribe: useReverbChannel,
  members: useReverbChannelMembers,
  directMessage: useReverbDirectMessage,
});
