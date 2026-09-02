import { Typography } from '@mui/material';
import { appRoutes } from '@router/app-router';
import { safeReturnTo } from '@app/utils/return-to';
import { authRoutes } from '@router/auth-router';
import { securityRoutes } from '@router/security-router';

import { useState } from 'react';
import { PiHandWaving, PiSignIn } from 'react-icons/pi';

import { Submit } from '@salvon/components/button';
import { Flex } from '@salvon/components/div';
import { Form, FormControl } from '@salvon/components/form';
import type { FormOnSubmit } from '@salvon/components/form/Form';
import { Link } from '@salvon/components/navigation';
import { useForm } from '@salvon/hooks/useForm';
import { useNavigate } from '@salvon/hooks/usePathNavigate';
import { useSearchParam } from '@salvon/hooks/useSearchParams';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { isMfaRequired } from '@salvon/utils/api';
import { notifyWarning } from '@salvon/utils/notify';
import { voc } from '@salvon/utils/object';
import { boolVal } from '@salvon/utils/type-transform';
import {
  requiredEmailRule,
  requiredRule,
} from '@salvon/utils/validation-rules';

import { AuthApi } from '@app/api/AuthApi';
import AltAccentIcon from '@app/components/layout/AltAccentIcon';

export default function Page() {
  const emailFromUrl = useSearchParam('email');
  const form = useForm({ defaultValues: { email: emailFromUrl } });
  const [loading, setLoading] = useState(false);
  const returnTo = useSearchParam('return_to');
  const t = useTranslation();
  const navigate = useNavigate();
  const handleSubmit: FormOnSubmit = async (data) => {
    setLoading(true);
    const { content } = await AuthApi.login(data);

    if (isMfaRequired(content)) {
      navigate(securityRoutes.mfa);
      return;
    }

    if (content?.message === 'invalid_credentials') {
      setLoading(false);
      notifyWarning(t('page.login.invalid_credentials'));
      return;
    }

    if (content?.message === 'account_inactive') {
      setLoading(false);
      notifyWarning(t('page.login.account_inactive'));
      return;
    }

    window.location.href = safeReturnTo(returnTo, appRoutes.index.path);
  };

  const { watch } = form;

  const email = watch('email');

  return (
    <Flex column fh gap={1} justify="center" pt={2} fw>
      <Flex center column>
        <AltAccentIcon icon={<PiHandWaving size={28} />} />
        <Typography
          variant="h4"
          sx={{
            fontWeight: 400,
            color: 'text.primary',
            textAlign: 'center',
            fontSize: '1.8rem',
          }}
        >
          {t('page.login.title')}
        </Typography>
        <Form onSubmit={handleSubmit} form={form}>
          <Flex center mt={2}>
            <Flex column gap={1} fw>
              <FormControl
                variant="text"
                name="email"
                label={t('email')}
                placeholder={t('email_placeholder')}
                rules={requiredEmailRule(t)}
                autoComplete="off"
              />
              <FormControl
                variant="password"
                name="password"
                label={t('password')}
                placeholder="********"
                rules={requiredRule(t)}
              />
              <Flex justify="space-between" wrap gap={1} fw align="center">
                <FormControl
                  name="remember_me"
                  variant="checkbox"
                  label={t('page.login.remember_me')}
                  defaultValue={false}
                />
                <Link
                  path={authRoutes.forgot_password.path}
                  pathParams={{
                    query: {
                      ...voc(boolVal(email && email.length > 0), { email }),
                    },
                  }}
                  color="textPrimary"
                >
                  {t('page.login.forgot_password')}
                </Link>
              </Flex>
              <Submit
                loading={loading}
                color="primary"
                variant="contained"
                icon={<PiSignIn />}
              >
                {t('page.login.submit')}
              </Submit>
            </Flex>
          </Flex>
        </Form>
      </Flex>
    </Flex>
  );
}
