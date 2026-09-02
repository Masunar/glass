import FormInputText from './FormInputText';

import type { FormInputNumberProps } from '@salvon/components/form/control/types';
import { intVal } from '@salvon/utils/type-transform';

export default function FormInputInteger({
  min,
  max,
  ...props
}: FormInputNumberProps) {
  return (
    <FormInputText
      {...props}
      type="number"
      onInput={(e) => {
        const v = e.target.value;
        let val = intVal(v);

        if (min && val < min) {
          val = min;
        }

        if (max && val > max) {
          val = max;
        }

        e.target.value = val;
      }}
    />
  );
}
