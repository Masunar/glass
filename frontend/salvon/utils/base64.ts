import { jsonDecode, jsonEncode } from '@salvon/utils/json';

export const toBase64 = (data: any): string => {
  return btoa(data);
};

export const fromBase64 = (data: string): any => {
  return atob(data);
};

export const toBase64Utf8 = (str: string): string => {
  const encoder = new TextEncoder();
  const utf8Bytes = encoder.encode(str);

  return btoa(String.fromCharCode(...utf8Bytes));
};

export const fromBase64Utf8 = (base64String: string): string => {
  const binaryString = atob(base64String);

  const bytes = new Uint8Array(binaryString.length);
  for (let i = 0; i < binaryString.length; i++) {
    bytes[i] = binaryString.charCodeAt(i);
  }

  const decoder = new TextDecoder('utf-8');
  return decoder.decode(bytes);
};

export const objectToBase64Utf8 = (data: any): string => {
  return toBase64Utf8(jsonEncode(data));
};

export const base64Utf8ToObject = (data: any): any => {
  return jsonDecode(fromBase64Utf8(data));
};
