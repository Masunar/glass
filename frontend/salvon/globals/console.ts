import env from '@app_env';

declare global {
  interface Console {
    devlog(...args: any): void;
  }
}

const devLog = (...args: any) => {
  if (!env.dev) {
    return;
  }

  console.log(...args);
};

console.devlog = devLog;
