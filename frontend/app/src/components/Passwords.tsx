import { LinearProgress, Typography } from '@mui/material';

import { useState } from 'react';

import { FormControl } from '@salvon/components/form';
import { useCurrentForm } from '@salvon/hooks/useForm';
import { useTranslation } from '@salvon/hooks/useTranslation';
import {
  measurePasswordStrength,
  primaryStrength,
} from '@salvon/utils/password-strength';
import {
  confirmPasswordRule,
  passwordRule,
} from '@salvon/utils/validation-rules';

export default function Passwords() {
  const form = useCurrentForm();
  const t = useTranslation();

  const [strength, setStrength] = useState(primaryStrength);

  const password = form.watch('password');

  return (
    <>
      <FormControl
        variant="password"
        name="password"
        label={t('password')}
        rules={passwordRule(t)}
        placeholder={t('password_placeholder')}
        required
        onChange={(v) => {
          setStrength(measurePasswordStrength(v));
        }}
      />
      <FormControl
        variant="password"
        name="confirm_password"
        label={t('confirm_password')}
        rules={confirmPasswordRule(t, password)}
        placeholder={t('password_placeholder')}
        required
      />
      <LinearProgress
        value={strength.strength}
        variant="determinate"
        color={strength.color}
      />
      <Typography>{t(strength.translation_key)}</Typography>
    </>
  );
}
