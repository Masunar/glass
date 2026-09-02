import { arrayFromLength } from '@salvon/utils/array';

declare global {
  interface ArrayConstructor {
    fromLength: (length: number, fill: string) => string[];
  }
}

Array.fromLength = arrayFromLength;
