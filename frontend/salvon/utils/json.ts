export const jsonEncode = (data: any) => {
  return JSON.stringify(data);
};

export const jsonDecode = (data: any) => {
  return JSON.parse(data);
};

export const isValidJson = (value: string): boolean => {
  try {
    JSON.parse(value);
  } catch {
    return false;
  }

  return true;
};
