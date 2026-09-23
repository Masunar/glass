import { useEffect, useState } from 'react';
import { PiTrash, PiWarningCircle } from 'react-icons/pi';

import { Button } from '@salvon/components/button';
import { Form } from '@salvon/components/form';
import { useForm } from '@salvon/hooks/useForm';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { validationCompleted } from '@salvon/utils/api-validation';
import { notifyError, notifySuccess } from '@salvon/utils/notify';

import type { AlertBoard, AlertRuleRow } from '@app/api/AlertsApi';
import { AlertsApi } from '@app/api/AlertsApi';
import Drawer, { DrawerColumn } from '@app/components/drawer/Drawer';
import Field, { Choice, Toggle } from '@app/components/drawer/Field';
import Fieldset, { FieldNote, FieldRow } from '@app/components/drawer/Fieldset';

type Props = {
  board: AlertBoard;
  /** `null` — nowa reguła; wiersz — edycja istniejącej. */
  rule: AlertRuleRow | null;
  open: boolean;
  onClose: () => void;
  onSaved: () => void;
};

/**
 * Edytor reguły alertu.
 *
 * Typ warunku pochodzi z katalogu i **jest jedynym polem, którego nie
 * da się wymyślić**. Reszta — próg, etykieta, kolor, włączenie — to
 * dane. Ustawienie progu pod typem zmienia się razem z nim, bo każdy
 * typ deklaruje własne parametry; pola nie są wspólne dla wszystkich
 * reguł i nie udają, że są.
 *
 * Zmiana warunku **zamyka wystąpienia policzone starym progiem**. To
 * widać w stopce przed zapisem, a nie dopiero w komunikacie po nim.
 */
export default function RuleDrawer({
  board,
  rule,
  open,
  onClose,
  onSaved,
}: Props) {
  const t = useTranslation();
  const form = useForm();
  const [type, setType] = useState<string>('');
  const [statuses, setStatuses] = useState<Set<string>>(new Set());
  const [busy, setBusy] = useState(false);

  const definition = board.catalog.find((item) => item.type === type) ?? null;

  useEffect(() => {
    if (!open) {
      return;
    }

    const first = board.catalog[0]?.type ?? '';
    const current = rule?.type ?? first;
    const chosen = board.catalog.find((item) => item.type === current);

    setType(chosen ? current : first);

    const fromRule = rule?.params.statuses;
    const fallback = chosen?.parameters.find((p) => p.type === 'statuses');

    setStatuses(
      new Set(
        Array.isArray(fromRule)
          ? (fromRule as string[])
          : Array.isArray(fallback?.default)
            ? fallback.default
            : [],
      ),
    );

    form.reset({
      // Typ siedzi i w formularzu, i w stanie: formularz go pokazuje,
      // stan decyduje, ktore parametry sie rysuja.
      type: chosen ? current : first,
      code: rule?.code ?? '',
      name: rule?.name ?? '',
      label: rule?.label ?? '',
      color: rule?.color ?? '',
      module: rule?.module ?? chosen?.module ?? '',
      days: String(rule?.params.days ?? 1),
      is_active: rule?.is_active ?? true,
    });
  }, [open, rule]);

  const toggleStatus = (code: string) => {
    const next = new Set(statuses);

    if (next.has(code)) {
      next.delete(code);
    } else {
      next.add(code);
    }

    setStatuses(next);
  };

  const submit = async (data: any) => {
    setBusy(true);

    const params: Record<string, unknown> = {};

    for (const parameter of definition?.parameters ?? []) {
      if (parameter.type === 'days') {
        params.days = Number(data.days ?? 1);
      }

      if (parameter.type === 'statuses') {
        params.statuses = [...statuses];
      }
    }

    const payload = {
      code: String(data.code ?? ''),
      name: String(data.name ?? ''),
      label: String(data.label ?? ''),
      type,
      module: data.module ? String(data.module) : undefined,
      color: data.color ? String(data.color) : null,
      is_active: Boolean(data.is_active),
      params,
    };

    const { content, response } =
      rule === null
        ? await AlertsApi.create(payload)
        : await AlertsApi.update(rule.id, payload);

    setBusy(false);

    if (!validationCompleted(content, form.setError, t)) {
      return;
    }

    if (!response.success) {
      notifyError(t('api.ise'));

      return;
    }

    notifySuccess(t('page.alerts.saved'));
    onSaved();
  };

  const remove = async () => {
    if (rule === null) {
      return;
    }

    setBusy(true);
    const { response } = await AlertsApi.remove(rule.id);
    setBusy(false);

    if (!response.success) {
      notifyError(t('api.ise'));

      return;
    }

    notifySuccess(t('page.alerts.deleted'));
    onSaved();
  };

  return (
    <Drawer
      open={open}
      onClose={onClose}
      kicker={t('page.menu.alerts')}
      title={rule?.name ?? t('page.alerts.new')}
      banner={
        rule !== null && (rule.open ?? 0) > 0 ? (
          <div className="ge-note ge-note--warn ge-acc__banner">
            <PiWarningCircle />{' '}
            {t('page.alerts.reach', { count: rule.open ?? 0 })}
          </div>
        ) : undefined
      }
      foot={
        <>
          {rule !== null && (
            <Button
              variant="text"
              icon={<PiTrash />}
              disabled={busy}
              onClick={() => void remove()}
            >
              {t('delete')}
            </Button>
          )}
          <div className="ge-drawer__foot-end">
            <Button variant="text" onClick={onClose}>
              {t('cancel')}
            </Button>
            <Button
              variant="contained"
              loading={busy}
              onClick={() => void form.handleSubmit(submit)()}
            >
              {t('save')}
            </Button>
          </div>
        </>
      }
    >
      <Form form={form} onSubmit={submit}>
        <DrawerColumn>
          <Fieldset tone="ident" label={t('page.alerts.what')}>
            <FieldRow columns="1fr">
              <Choice
                name="type"
                label={t('page.alerts.type')}
                required
                options={board.catalog.map((item) => ({
                  value: item.type,
                  label: item.label,
                }))}
                onChange={(value) => setType(value)}
              />
            </FieldRow>
            <FieldNote>{t('page.alerts.type_hint')}</FieldNote>

            <FieldRow columns="1fr 1fr" paddingTop={12}>
              <Field name="code" label={t('page.alerts.code')} required />
              <Field name="name" label={t('name')} required />
            </FieldRow>
          </Fieldset>

          {definition !== null && definition.parameters.length > 0 && (
            <Fieldset tone="terms" label={t('page.alerts.when')}>
              {definition.parameters.map((parameter) =>
                parameter.type === 'days' ? (
                  <div key={parameter.key}>
                    <FieldRow columns="120px 1fr">
                      <Field
                        name="days"
                        label={parameter.label}
                        type="number"
                        emphasis="num"
                        required
                      />
                    </FieldRow>
                    <FieldNote>{parameter.hint}</FieldNote>
                  </div>
                ) : (
                  <div key={parameter.key}>
                    <div className="ge-uf__label">{parameter.label}</div>
                    <div className="ge-alerts__statuses">
                      {board.statuses.map((status) => (
                        <label className="ge-alerts__status" key={status.code}>
                          <input
                            type="checkbox"
                            checked={statuses.has(status.code)}
                            onChange={() => toggleStatus(status.code)}
                          />
                          {status.name}
                        </label>
                      ))}
                    </div>
                    <FieldNote>{parameter.hint}</FieldNote>
                  </div>
                ),
              )}
            </Fieldset>
          )}

          <Fieldset tone="contact" label={t('page.alerts.how')}>
            <FieldRow columns="1fr 140px">
              <Field name="label" label={t('page.alerts.label')} required />
              <Field
                name="color"
                label={t('page.alerts.color')}
                placeholder="#b45309"
              />
            </FieldRow>
            <FieldNote>{t('page.alerts.label_hint')}</FieldNote>

            <FieldRow columns="1fr" paddingTop={12}>
              <Choice
                name="module"
                label={t('page.alerts.module')}
                options={board.modules.map((module) => ({
                  value: module.key,
                  label: module.name,
                }))}
              />
            </FieldRow>

            <FieldRow columns="1fr" paddingTop={12}>
              <Toggle name="is_active" label={t('page.alerts.active')} />
            </FieldRow>
            <FieldNote>{t('page.alerts.active_hint')}</FieldNote>
          </Fieldset>
        </DrawerColumn>
      </Form>
    </Drawer>
  );
}
