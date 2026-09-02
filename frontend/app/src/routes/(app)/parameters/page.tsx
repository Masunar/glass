import { Box, Typography } from '@mui/material';

import Heading from '@app-components/Heading';
import { appRoutes } from '@router/app-router';

import { parameterGroups } from './_components/groups';
import { useEffect, useMemo, useState } from 'react';
import { PiFloppyDisk, PiSlidersHorizontal } from 'react-icons/pi';

import { Submit } from '@salvon/components/button';
import { Card } from '@salvon/components/card';
import { Flex } from '@salvon/components/div';
import { Form, FormControl } from '@salvon/components/form';
import type { FormOnSubmit } from '@salvon/components/form/Form';
import { useForm } from '@salvon/hooks/useForm';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { validationCompleted } from '@salvon/utils/api-validation';
import { notifySuccess } from '@salvon/utils/notify';

import { type Parameter, ParametersApi } from '@app/api/ParametersApi';

export default function Page() {
  const t = useTranslation();
  const form = useForm();
  const [parameters, setParameters] = useState<Parameter[]>([]);
  const [saving, setSaving] = useState(false);

  const byKey = useMemo(
    () => new Map(parameters.map((parameter) => [parameter.key, parameter])),
    [parameters],
  );

  const load = async () => {
    const { content } = await ParametersApi.list();
    const loaded: Parameter[] = content?.data?.parameters ?? [];

    setParameters(loaded);
    form.reset(
      Object.fromEntries(
        loaded.map((parameter) => [parameter.key, parameter.value ?? '']),
      ),
    );
  };

  useEffect(() => {
    void load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const dirtyKeys = Object.keys(form.formState.dirtyFields);

  const handleSubmit: FormOnSubmit = async (data) => {
    if (dirtyKeys.length === 0) {
      return;
    }

    setSaving(true);

    // Wysylamy wylacznie zmienione pola. Zapis jest niepodzielny -
    // odrzucenie jednej wartosci wstrzymuje pozostale.
    const values = Object.fromEntries(
      dirtyKeys.map((key) => [key, data[key] === '' ? null : data[key]]),
    );

    const { content } = await ParametersApi.save(values);

    setSaving(false);

    if (!validationCompleted(content, form.setError, t)) {
      return;
    }

    notifySuccess(t('page.parameters.saved'));
    await load();
  };

  return (
    <Form onSubmit={handleSubmit} form={form}>
      <Flex column gap={2}>
        <Heading
          returnTo={{ path: appRoutes.index }}
          icon={<PiSlidersHorizontal />}
          title={t('page.parameters.title')}
        >
          <Submit
            loading={saving}
            disabled={dirtyKeys.length === 0}
            color="primary"
            variant="contained"
            icon={<PiFloppyDisk />}
          >
            {dirtyKeys.length > 0
              ? t('page.parameters.save_count', { count: dirtyKeys.length })
              : t('save')}
          </Submit>
        </Heading>

        <Typography variant="body2" sx={{ color: 'text.secondary', mt: -1 }}>
          {t('page.parameters.lead')}
        </Typography>

        {parameterGroups.map((group) => (
          <Card key={group.titleKey}>
            <Flex column gap={2}>
              <Typography sx={{ fontWeight: 600 }}>
                {t(group.titleKey)}
              </Typography>

              {group.leadKey && (
                <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                  {t(group.leadKey)}
                </Typography>
              )}

              {group.keys.map((key) => {
                const parameter = byKey.get(key);

                if (!parameter) {
                  return null;
                }

                const isDirty = dirtyKeys.includes(key);
                const numeric =
                  parameter.type === 'number' || parameter.type === 'percent';
                const choice = parameter.type === 'choice';

                return (
                  <Box
                    key={key}
                    sx={{
                      borderLeft: '3px solid',
                      borderColor: isDirty ? 'primary.main' : 'transparent',
                      pl: 1.5,
                      transition: 'border-color .15s',
                    }}
                  >
                    {choice ? (
                      <FormControl
                        variant="select"
                        name={key}
                        label={t(`page.parameters.field.${key}`)}
                        helperText={parameter.description ?? undefined}
                        options={parameter.options.map((option) => ({
                          label: t(`page.parameters.option.${option}`),
                          value: option,
                        }))}
                      />
                    ) : (
                      <FormControl
                        variant={numeric ? 'number' : 'text'}
                        name={key}
                        label={t(`page.parameters.field.${key}`)}
                        helperText={parameter.description ?? undefined}
                        slotProps={
                          parameter.type === 'percent'
                            ? { input: { endAdornment: '%' } }
                            : undefined
                        }
                      />
                    )}
                  </Box>
                );
              })}
            </Flex>
          </Card>
        ))}
      </Flex>
    </Form>
  );
}
