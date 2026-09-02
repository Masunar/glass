import FormInputText from './FormInputText';
import { useState } from 'react';
import { PiEye, PiEyeSlash } from 'react-icons/pi';

import { InputAdornment } from '@salvon/components/form';
import type { FormInputTextProps } from '@salvon/components/form/control/types';
import { useTranslation } from '@salvon/hooks/useTranslation';

export default function FormInputPassword(props: FormInputTextProps) {
  const { slotProps } = props;
  const [showPassword, setShowPassword] = useState(false);
  const t = useTranslation();

  return (
    <FormInputText
      autoComplete="off"
      {...props}
      type={showPassword ? 'text' : 'password'}
      slotProps={{
        ...slotProps,
        input: {
          endAdornment: (
            <InputAdornment
              icon={showPassword ? <PiEye /> : <PiEyeSlash />}
              label={t(showPassword ? 'hide' : 'show')}
              onClick={() => setShowPassword((v) => !v)}
            />
          ),
          ...slotProps?.input,
        },
      }}
    />
  );
}
