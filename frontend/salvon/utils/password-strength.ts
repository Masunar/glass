import type { LinearProgressPropsColorOverrides } from '@mui/material';
import type { OverridableStringUnion } from '@mui/types';

type PasswordStrength = {
  strength: number;
  color: OverridableStringUnion<
    | 'primary'
    | 'secondary'
    | 'error'
    | 'info'
    | 'success'
    | 'warning'
    | 'inherit',
    LinearProgressPropsColorOverrides
  >;
  translation_key: string;
};

export const primaryStrength: PasswordStrength = {
  strength: 0,
  color: 'secondary',
  translation_key: 'validation.password.strength',
};

export const measurePasswordStrength = (
  password: string = '',
): PasswordStrength => {
  let strength = 0;
  const validateRegex = ['[A-Z]', '[a-z]', '[0-9]', '\\W'];

  validateRegex.forEach((regex) => {
    if (new RegExp(regex).test(password)) {
      strength += 1;
    }
  });

  if (password.length > 0 && !new RegExp('[^ ]{8,16}').test(password)) {
    strength = 1;
  }

  if (password.length > 12) {
    strength += 1;
  }

  if (password.length > 20) {
    strength += 1;
  }

  switch (strength) {
    default:
    case 0:
      return primaryStrength;
    case 1:
    case 2:
      return {
        strength: 33,
        color: 'error',
        translation_key: 'validation.password.weak',
      };
    case 3:
    case 4:
      return {
        strength: 66,
        color: 'info',
        translation_key: 'validation.password.medium',
      };
    case 5:
      return {
        strength: 100,
        color: 'success',
        translation_key: 'validation.password.strong',
      };
  }
};
