import FormInputText from './FormInputText';

import type { FormInputNumberProps } from '@salvon/components/form/control/types';
import { sanitizeStringNumber } from '@salvon/utils/number';

export default function FormInputNumber({
  min,
  max,
  precision,
  ...props
}: FormInputNumberProps) {
  return (
    <FormInputText
      {...props}
      onInput={(e) => {
        const v = e.target.value;
        let val = sanitizeStringNumber(v, precision);
        const numeric = +val;
        const comparable = !isNaN(numeric) && val !== '';

        if (comparable && min && numeric < min) {
          val = `${min}`;
        }

        if (comparable && max && numeric > max) {
          val = `${max}`;
        }

        e.target.value = val;
      }}
    />
  );
}
