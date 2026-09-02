import { Typography } from '@mui/material';
import { authRoutes } from '@router/auth-router';

import { useState } from 'react';
import { PiUserSwitch } from 'react-icons/pi';

import { Submit } from '@salvon/components/button';
import { Flex } from '@salvon/components/div';
import { Form, FormControl } from '@salvon/components/form';
import type { FormOnSubmit } from '@salvon/components/form/Form';
import { useForm } from '@salvon/hooks/useForm';
import { useNavigate } from '@salvon/hooks/usePathNavigate';
import { useRouteParam } from '@salvon/hooks/useRouteParam';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { isBadRequestScope } from '@salvon/utils/api';
import { failedResponse } from '@salvon/utils/api-validation';
import { notifySuccess, notifyWarning } from '@salvon/utils/notify';
import { requiredEmailRule } from '@salvon/utils/validation-rules';

import { AuthApi } from '@app/api/AuthApi';
import Passwords from '@app/components/Passwords';
import AltAccentIcon from '@app/components/layout/AltAccentIcon';

export default function Page() {
  const form = useForm();
  const t = useTranslation();
  const [loading, setLoading] = useState(false);
  const token = useRouteParam('token');

  const navigate = useNavigate();

  const handleSubmit: FormOnSubmit = async (data: any) => {
    setLoading(true);
    const res = await AuthApi.resetPassword({ ...data, token });
    setLoading(false);

    if (
      isBadRequestScope(res.response) &&
      res.content.message === 'invalid_token'
    ) {
      notifyWarning(t('page.reset_password.invalid_token'));
      return;
    }

    if (
      isBadRequestScope(res.response) &&
      res.content.message === 'invalid_user'
    ) {
      notifyWarning(t('page.reset_password.invalid_user'));
      return;
    }

    if (failedResponse(res, form.setError, t)) {
      return;
    }

    notifySuccess(t('page.reset_password.completed'));
    navigate(authRoutes.login);
  };

  return (
    <Flex column fh gap={1} justify="center" pt={2} fw>
      <Flex center column>
        <AltAccentIcon icon={<PiUserSwitch size={28} />} />
        <Typography
          variant="h4"
          sx={{
            fontWeight: 400,
            color: 'text.primary',
            textAlign: 'center',
            fontSize: '1.8rem',
          }}
        >
          {t('page.reset_password.title')}
        </Typography>
        <Form onSubmit={handleSubmit} form={form}>
          <Flex center mt={2}>
            <Flex
              column
              gap={1}
              sx={{
                maxWidth: {
                  xs: '95%',
                  sm: '90%',
                  xl: 550,
                  fhd: 700,
                  uhd: 1000,
                },
              }}
              fw
            >
              <FormControl
                variant="text"
                name="email"
                label={t('email')}
                rules={requiredEmailRule(t)}
                placeholder={t('email_placeholder')}
                required
              />
              <Passwords />
              <Submit
                color="primary"
                icon={<PiUserSwitch />}
                sx={{ mt: 2 }}
                loading={loading}
              >
                {t('page.reset_password.submit')}
              </Submit>
            </Flex>
          </Flex>
        </Form>
      </Flex>
      <Flex />
    </Flex>
  );
}
