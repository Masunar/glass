export type ChannelVisibility = 'public' | 'private' | 'encrypted' | 'presence';

const prefixes: Record<ChannelVisibility, string> = {
  public: '',
  private: 'private-',
  encrypted: 'private-encrypted-',
  presence: 'presence-',
};

export const channelName = (
  name: string,
  visibility: ChannelVisibility,
): string => `${prefixes[visibility]}${name}`;
