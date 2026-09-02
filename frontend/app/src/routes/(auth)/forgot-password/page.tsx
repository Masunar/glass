import { Typography } from '@mui/material';
import { authRoutes } from '@router/auth-router';

import { useState } from 'react';
import { PiUserSwitch } from 'react-icons/pi';

import { Submit } from '@salvon/components/button';
import { Button } from '@salvon/components/button';
import { Flex } from '@salvon/components/div';
import { Form, FormControl } from '@salvon/components/form';
import type { FormOnSubmit } from '@salvon/components/form/Form';
import { Link } from '@salvon/components/navigation';
import { useSearchParam } from '@salvon/hooks/useSearchParams';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { isFatalResponse, isThrottled } from '@salvon/utils/api';
import { notifyError, notifyWarning } from '@salvon/utils/notify';
import { requiredEmailRule } from '@salvon/utils/validation-rules';

import { AuthApi } from '@app/api/AuthApi';
import AltAccentIcon from '@app/components/layout/AltAccentIcon';

export default function Page() {
  const t = useTranslation();
  const [loading, setLoading] = useState(false);
  const [completed, setCompleted] = useState(false);

  const urlEmail = useSearchParam('email') || '';

  const handleSubmit: FormOnSubmit = async (data: any) => {
    setLoading(true);
    const res = await AuthApi.forgotPassword(data.email);
    setLoading(false);

    if (isThrottled(res.response)) {
      notifyWarning(t('page.forgot_password.throttle'));
      return;
    }

    if (isFatalResponse(res.response)) {
      notifyError(t('api.ise'));
      return;
    }

    setCompleted(true);
  };

  if (completed) {
    return (
      <Flex column fh gap={1} justify="center" pt={2} fw>
        <Flex center column gap={1}>
          <AltAccentIcon icon={<PiUserSwitch size={28} />} />
          <Typography
            variant="h4"
            sx={{ fontWeight: 400, color: 'text.primary' }}
          >
            {t('page.forgot_password.completed')}
          </Typography>
          <Typography
            variant="body1"
            sx={{
              fontWeight: 400,
              color: 'text.primary',
              textAlign: 'center',
            }}
          >
            {t('page.forgot_password.completed_description')}
          </Typography>
          <Button
            path={authRoutes.login}
            sx={{
              mt: 2,
            }}
          >
            {t('page.forgot_password.back_to_login')}
          </Button>
        </Flex>
      </Flex>
    );
  }

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
          {t('page.forgot_password.title')}
        </Typography>
        <Form onSubmit={handleSubmit}>
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
                defaultValue={urlEmail}
              />
              <Submit
                color="primary"
                icon={<PiUserSwitch />}
                sx={{ mt: 2 }}
                loading={loading}
              >
                {t('page.forgot_password.submit')}
              </Submit>
              <Flex center>
                <Link path={authRoutes.login} color="textPrimary">
                  {t('page.forgot_password.back_to_login')}
                </Link>
              </Flex>
            </Flex>
          </Flex>
        </Form>
      </Flex>
      <Flex />
    </Flex>
  );
}
