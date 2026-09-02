export const objectValueFromPath = <T>(
  object: any,
  path: string,
): T | undefined => {
  const keys = path.split('.');

  let current: any = object;
  for (const key of keys) {
    if (current === null || current === undefined || !(key in current)) {
      return undefined;
    }

    current = current[key];
  }

  return current as T;
};

export const valuesOnCondition = (
  condition: boolean | undefined,
  value: any,
  falseValue: any = {},
) => {
  if (condition) {
    return value;
  }

  return falseValue;
};

/**
 * Shorthand alias for valuesOnCondition function.
 */
export const voc = valuesOnCondition;

export const generateGrid = ({
  rows,
  cols,
  defaultValue,
  rowPrefix,
  colPrefix,
}: {
  rows: number;
  cols: number;
  defaultValue: any;
  rowPrefix?: string;
  colPrefix?: string;
}): any => {
  const grid: any = {};
  for (let r = 0; r <= rows; r++) {
    const rowKey = `${rowPrefix}${r}`;
    grid[rowKey] = {};
    for (let c = 0; c <= cols; c++) {
      const colKey = `${colPrefix}${c}`;
      grid[rowKey][colKey] = defaultValue;
    }
  }
  return grid;
};

export const generateObjectArrayGrid = <T>(
  rows: number,
  cols: number,
  defaultValue: T,
): Record<string, T[]> => {
  const obj: Record<string, T[]> = {};
  for (let r = 0; r < rows; r++) {
    obj[r] = Array(cols).fill(defaultValue);
  }
  return obj;
};
