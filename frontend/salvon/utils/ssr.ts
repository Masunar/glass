export const isServer = (): boolean => {
  return typeof document === 'undefined';
};

export const isClient = (): boolean => {
  return !isServer();
};

export const getWindow = () => {
  if (typeof window === 'undefined') {
    return undefined;
  }

  return window;
};

export const getDocument = () => {
  if (typeof document === 'undefined') {
    return undefined;
  }

  return document;
};
