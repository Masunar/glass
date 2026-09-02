export const arrayFromLength = (length: number, fill: string = '') => {
  if (length < 1) {
    return [];
  }

  return Array(length).fill(fill);
};
