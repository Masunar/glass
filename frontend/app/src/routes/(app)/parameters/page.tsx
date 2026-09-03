import { parameterGroups } from './_components/groups';
import { useEffect, useMemo, useState } from 'react';
import { PiFloppyDisk } from 'react-icons/pi';

import { Button } from '@salvon/components/button';
import { Form, FormControl } from '@salvon/components/form';
import { useForm } from '@salvon/hooks/useForm';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { validationCompleted } from '@salvon/utils/api-validation';
import { notifySuccess } from '@salvon/utils/notify';

import { type Parameter, ParametersApi } from '@app/api/ParametersApi';

export default function Page() {
  const t = useTranslation();
  const form = useForm();
  const [parameters, setParameters] = useState<Parameter[]>([]);
  const [tab, setTab] = useState(parameterGroups[0].key);
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
  }, []);

  const dirtyKeys = Object.keys(form.formState.dirtyFields);

  /**
   * Ile niezapisanych zmian siedzi na każdej zakładce.
   *
   * Bez tego licznika przełączenie zakładki chowałoby zmianę z oczu —
   * a zapis obejmuje wszystkie naraz, więc człowiek musi wiedzieć, że
   * gdzieś indziej też coś tknął.
   */
  const dirtyPerTab = useMemo(() => {
    const counts: Record<string, number> = {};

    for (const group of parameterGroups) {
      counts[group.key] = group.keys.filter((key) =>
        dirtyKeys.includes(key),
      ).length;
    }

    return counts;
  }, [dirtyKeys]);

  const group =
    parameterGroups.find((item) => item.key === tab) ?? parameterGroups[0];

  const handleSubmit = async (data: Record<string, unknown>) => {
    if (dirtyKeys.length === 0) {
      return;
    }

    setSaving(true);

    // Wysylamy wylacznie zmienione pola, ze wszystkich zakladek naraz.
    // Zapis jest niepodzielny - odrzucenie jednej wartosci wstrzymuje
    // pozostale.
    const values: Record<string, string | null> = Object.fromEntries(
      dirtyKeys.map((key) => {
        const value = data[key];

        return [key, value === '' || value === null ? null : String(value)];
      }),
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
      <header className="ge-head">
        <div>
          <div className="ge-head__kicker">{t('page.module.adm')}</div>
          <h1 className="ge-head__title">{t('page.parameters.title')}</h1>
        </div>

        <div className="ge-head__actions">
          <Button
            type="submit"
            variant="contained"
            loading={saving}
            disabled={dirtyKeys.length === 0}
            icon={<PiFloppyDisk />}
          >
            {dirtyKeys.length > 0
              ? t('page.parameters.save_count', { count: dirtyKeys.length })
              : t('save')}
          </Button>
        </div>
      </header>

      <nav className="ge-filters" aria-label={t('page.parameters.tabs')}>
        {parameterGroups.map((item) => (
          <button
            key={item.key}
            type="button"
            className={item.key === tab ? 'is-active' : ''}
            onClick={() => setTab(item.key)}
          >
            {t(item.titleKey)}
            {dirtyPerTab[item.key] > 0 && (
              <span className="ge-filters__dot" aria-hidden="true" />
            )}
          </button>
        ))}
        <span className="ge-filters__end">{t('page.parameters.lead')}</span>
      </nav>

      <div className="ge-form">
        {group.leadKey && <p className="ge-lead">{t(group.leadKey)}</p>}

        <div
          className="ge-form__grid"
          style={{ ['--ge-cols' as string]: group.columns }}
        >
          {group.keys.map((key) => {
            const parameter = byKey.get(key);

            if (!parameter) {
              return null;
            }

            const dirty = dirtyKeys.includes(key);
            const numeric =
              parameter.type === 'number' || parameter.type === 'percent';

            return (
              <div
                key={key}
                // Niezapisane pole dostaje krawędź, nie ramkę — ta sama
                // zasada, co przy wierszu listy wymagającym decyzji.
                className={dirty ? 'ge-form__field is-dirty' : 'ge-form__field'}
              >
                {parameter.type === 'choice' ? (
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
              </div>
            );
          })}
        </div>
      </div>
    </Form>
  );
}
