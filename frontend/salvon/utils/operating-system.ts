export const isMac = (): boolean => {
  if (typeof navigator === 'undefined') {
    return false;
  }

  // navigator.platform is deprecated; prefer userAgentData where available.
  const platform =
    (navigator as any).userAgentData?.platform ?? navigator.userAgent;

  return /Mac|iP(hone|ad|od)/.test(platform);
};
