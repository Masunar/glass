import { Typography } from '@mui/material';

import type { UseFormReturn } from 'react-hook-form';
import { BiChevronLeft } from 'react-icons/bi';

import { Button } from '@salvon/components/button';
import { Flex } from '@salvon/components/div';
import { Form, FormControl } from '@salvon/components/form';
import { useTranslation } from '@salvon/hooks/useTranslation';

export type PasswordFormData = {
  current_password: string;
  password: string;
  password_confirmation: string;
};

type Props = {
  form: UseFormReturn<PasswordFormData>;
  onBack: () => void;
  onSubmit: (data: PasswordFormData) => Promise<void>;
  loading: boolean;
};

export default function ChangePassword({
  form,
  onBack,
  onSubmit,
  loading,
}: Props) {
  const t = useTranslation();

  return (
    <Flex column gap={2}>
      <Flex
        align="center"
        gap={0.5}
        sx={{ cursor: 'pointer', color: 'text.secondary' }}
        onClick={() => {
          onBack();
          form.reset();
        }}
      >
        <BiChevronLeft style={{ fontSize: 18 }} />
        <Typography variant="body2">
          {t('page.user_account.back_to_account')}
          {' / '}
          {t('page.user_account.change_password.title')}
        </Typography>
      </Flex>

      <Form onSubmit={onSubmit as any} form={form}>
        <Flex column gap={1.5}>
          <FormControl
            variant="password"
            name="current_password"
            label={t('page.user_account.change_password.current_password')}
            rules={{ required: true }}
            placeholder="••••••••"
          />
          <FormControl
            variant="password"
            name="password"
            label={t('page.user_account.change_password.new_password')}
            rules={{
              required: true,
              minLength: {
                value: 8,
                message: t('page.user_account.change_password.min_length'),
              },
            }}
            placeholder={t('page.user_account.change_password.min_length')}
          />
          <FormControl
            variant="password"
            name="password_confirmation"
            label={t('page.user_account.change_password.confirm_password')}
            rules={{
              required: true,
              validate: (v: string) =>
                v === form.getValues('password') ||
                t('page.user_account.change_password.passwords_mismatch'),
            }}
            placeholder="••••••••"
          />
          <Flex gap={1} justify="flex-end" mt={1}>
            <Button
              variant="outlined"
              onClick={() => {
                onBack();
                form.reset();
              }}
            >
              {t('cancel')}
            </Button>
            <Button type="submit" variant="contained" loading={loading}>
              {t('page.user_account.change_password.submit')}
            </Button>
          </Flex>
        </Flex>
      </Form>
    </Flex>
  );
}
