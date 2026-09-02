import { Typography } from '@mui/material';

import type { UseFormReturn } from 'react-hook-form';
import { BiChevronLeft } from 'react-icons/bi';

import { Button } from '@salvon/components/button';
import { Flex } from '@salvon/components/div';
import { Form, FormControl } from '@salvon/components/form';
import { useTranslation } from '@salvon/hooks/useTranslation';

export type EditFormData = {
  first_name: string;
  last_name: string;
  email: string;
  phone: string;
};

type Props = {
  form: UseFormReturn<EditFormData>;
  currentEmail: string;
  onBack: () => void;
  onSubmit: (data: EditFormData) => Promise<void>;
  loading: boolean;
};

export default function EditUser({
  form,
  currentEmail,
  onBack,
  onSubmit,
  loading,
}: Props) {
  const t = useTranslation();
  const emailChanged = form.watch('email') !== currentEmail;

  const handleSubmit = () => form.handleSubmit(onSubmit as any)();

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
          {t('page.user_account.edit.title')}
        </Typography>
      </Flex>

      <Form onSubmit={onSubmit as any} form={form}>
        <Flex column gap={1.5}>
          <FormControl
            variant="text"
            name="first_name"
            label={t('page.user_account.edit.username')}
            rules={{ required: true }}
          />
          <FormControl variant="text" name="last_name" label={t('last_name')} />
          <FormControl
            variant="text"
            name="email"
            label={t('page.user_account.edit.email')}
            rules={{
              required: true,
              pattern: {
                value: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
                message: t('page.user_account.edit.email_invalid'),
              },
            }}
          />
          <FormControl variant="text" name="phone" label={t('phone')} />
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
            <Button
              type="button"
              variant="contained"
              loading={loading}
              confirm={
                emailChanged
                  ? {
                      label: (
                        <Typography variant="body2" sx={{ maxWidth: 250 }}>
                          {t('page.user_account.edit.email_change_confirm')}
                        </Typography>
                      ),
                      onConfirm: (close) => {
                        close();
                        handleSubmit();
                      },
                    }
                  : undefined
              }
              onClick={!emailChanged ? handleSubmit : undefined}
            >
              {t('page.user_account.edit.submit')}
            </Button>
          </Flex>
        </Flex>
      </Form>
    </Flex>
  );
}
