import HistoryDrawer from './_components/HistoryDrawer';
import {
  parameterBands,
  parameterTabs,
  parameterUnits,
} from './_components/groups';
import { useEffect, useMemo, useState } from 'react';

import { Button } from '@salvon/components/button';
import { Form, FormControl } from '@salvon/components/form';
import { useCurrentForm, useForm } from '@salvon/hooks/useForm';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { validationCompleted } from '@salvon/utils/api-validation';
import { notifySuccess } from '@salvon/utils/notify';

import {
  type Parameter,
  type ParameterBoard,
  type ParameterImpact,
  ParametersApi,
} from '@app/api/ParametersApi';

const columns = [
  'page.parameters.column.parameter',
  'page.parameters.column.value',
  'page.parameters.column.applies_to',
  'page.parameters.column.impact',
  'page.parameters.column.changed',
];

export default function Page() {
  const t = useTranslation();
  const form = useForm();
  const [board, setBoard] = useState<ParameterBoard | null>(null);
  const [impact, setImpact] = useState<ParameterImpact | null>(null);
  const [tab, setTab] = useState('all');
  const [query, setQuery] = useState('');
  const [saving, setSaving] = useState(false);
  const [historyOpen, setHistoryOpen] = useState(false);

  const parameters = board?.parameters ?? [];
  const byKey = useMemo(
    () => new Map(parameters.map((parameter) => [parameter.key, parameter])),
    [parameters],
  );

  const load = async () => {
    const { content } = await ParametersApi.list();
    const data: ParameterBoard | undefined = content?.data;

    if (!data) {
      return;
    }

    setBoard(data);
    setImpact(null);
    form.reset(
      Object.fromEntries(
        data.parameters.map((parameter) => [
          parameter.key,
          parameter.value ?? '',
        ]),
      ),
    );
  };

  useEffect(() => {
    void load();
  }, []);

  const values = form.watch();
  const dirtyKeys = Object.keys(form.formState.dirtyFields);

  /**
   * Wpływ liczy serwer, bo wzór wyceny żyje po jego stronie — i tylko
   * on zna kolejność mnożników. Zapytanie idzie po odstaniu, żeby
   * pisanie w polu nie było serią zapytań.
   */
  useEffect(() => {
    if (dirtyKeys.length === 0) {
      setImpact(null);
      return;
    }

    const timer = setTimeout(() => {
      void (async () => {
        const proposed = Object.fromEntries(
          dirtyKeys.map((key) => [key, String(values[key] ?? '')]),
        );

        const { content } = await ParametersApi.preview(proposed);

        setImpact(content?.data ?? null);
      })();
    }, 300);

    return () => clearTimeout(timer);
  }, [JSON.stringify(dirtyKeys.map((key) => [key, values[key]]))]);

  const impactFor = (key: string) =>
    impact?.parameters.find((entry) => entry.key === key) ?? null;

  const activeTab =
    parameterTabs.find((item) => item.key === tab) ?? parameterTabs[0];

  const matches = (parameter: Parameter) => {
    const needle = query.trim().toLowerCase();

    if (needle === '') {
      return true;
    }

    return (
      t(`page.parameters.field.${parameter.key}`)
        .toLowerCase()
        .includes(needle) ||
      (parameter.description ?? '').toLowerCase().includes(needle)
    );
  };

  const bands = useMemo(
    () =>
      parameterBands
        .filter((band) => activeTab.bands.includes(band.key))
        .map((band) => ({
          ...band,
          rows: band.keys
            .map((key) => byKey.get(key))
            .filter(
              (parameter): parameter is Parameter => parameter !== undefined,
            )
            .filter(matches)
            .filter(
              (parameter) =>
                tab !== 'changed' || dirtyKeys.includes(parameter.key),
            ),
        }))
        .filter((band) => band.rows.length > 0),
    [activeTab, byKey, query, tab, dirtyKeys.join(',')],
  );

  const countFor = (key: string) => {
    const item = parameterTabs.find((tabItem) => tabItem.key === key);

    if (!item) {
      return 0;
    }

    return parameterBands
      .filter((band) => item.bands.includes(band.key))
      .reduce(
        (total, band) =>
          total + band.keys.filter((paramKey) => byKey.has(paramKey)).length,
        0,
      );
  };

  const handleSubmit = async (data: Record<string, unknown>) => {
    if (dirtyKeys.length === 0) {
      return;
    }

    setSaving(true);

    // Wysylamy wylacznie zmienione pola. Zapis jest niepodzielny -
    // odrzucenie jednej wartosci wstrzymuje pozostale.
    const payload: Record<string, string | null> = Object.fromEntries(
      dirtyKeys.map((key) => {
        const value = data[key];

        return [key, value === '' || value === null ? null : String(value)];
      }),
    );

    const { content } = await ParametersApi.save(payload);

    setSaving(false);

    if (!validationCompleted(content, form.setError, t)) {
      return;
    }

    notifySuccess(t('page.parameters.saved'));
    await load();
  };

  const nextVersion = (board?.version ?? 0) + 1;

  return (
    <Form onSubmit={handleSubmit} form={form}>
      <header className="ge-head">
        <div>
          <div className="ge-head__kicker">{t('page.module.adm')}</div>
          <h1 className="ge-head__title">{t('page.parameters.title')}</h1>
          <div className="ge-panel__meta">
            {[
              t('page.parameters.version', { count: board?.version ?? 0 }),
              t('page.parameters.count', { count: parameters.length }),
              dirtyKeys.length > 0
                ? t('page.parameters.unsaved', { count: dirtyKeys.length })
                : null,
            ]
              .filter(Boolean)
              .join(' · ')}
          </div>
        </div>

        <div className="ge-head__actions">
          <input
            className="ge-search"
            type="search"
            value={query}
            placeholder={t('page.parameters.search')}
            aria-label={t('page.parameters.search')}
            onChange={(event) => setQuery(event.target.value)}
          />
          <Button variant="outlined" onClick={() => setHistoryOpen(true)}>
            {t('page.parameters.history')}
          </Button>
          <Button
            type="submit"
            variant="contained"
            loading={saving}
            disabled={dirtyKeys.length === 0}
          >
            {t('page.parameters.save_version', { count: nextVersion })}
          </Button>
        </div>
      </header>

      <nav className="ge-filters" aria-label={t('page.parameters.tabs')}>
        {parameterTabs.map((item) => (
          <button
            key={item.key}
            type="button"
            className={item.key === tab ? 'is-active' : ''}
            onClick={() => setTab(item.key)}
          >
            {t(item.labelKey)} {countFor(item.key)}
          </button>
        ))}
        {dirtyKeys.length > 0 && (
          <button
            type="button"
            className={tab === 'changed' ? 'is-warn is-active' : 'is-warn'}
            onClick={() => setTab('changed')}
          >
            {t('page.parameters.tab.changed')} {dirtyKeys.length}
          </button>
        )}
      </nav>

      <div className="ge-params">
        <div className="ge-params__head">
          {columns.map((key) => (
            <span key={key}>{t(key)}</span>
          ))}
        </div>

        {bands.map((band) => (
          <div key={band.key}>
            <div className={`ge-params__band ge-params__band--${band.tone}`}>
              <span className="ge-params__band-title">{t(band.titleKey)}</span>
              {band.leadKey && (
                <span className="ge-params__band-meta">{t(band.leadKey)}</span>
              )}
            </div>

            {band.rows.map((parameter) => (
              <Row
                key={parameter.key}
                parameter={parameter}
                dirty={dirtyKeys.includes(parameter.key)}
                impact={impactFor(parameter.key)}
                t={t}
              />
            ))}
          </div>
        ))}

        {bands.length === 0 && (
          <div className="ge-empty">{t('page.parameters.empty')}</div>
        )}
      </div>

      {dirtyKeys.length > 0 && (
        <div className="ge-params__foot">
          <strong>
            {t('page.parameters.unsaved', { count: dirtyKeys.length })}
          </strong>
          <span>
            {impact?.average_percent === null || impact === null
              ? t('page.parameters.impact_pending')
              : impact.average_percent === 0
                ? t('page.parameters.impact_none')
                : t('page.parameters.impact_average', {
                    percent: formatPercent(impact.average_percent),
                  })}
          </span>
          <span className="ge-params__foot-end">
            <Button variant="text" onClick={() => void load()}>
              {t('page.parameters.discard')}
            </Button>
            <Button type="submit" variant="contained" loading={saving}>
              {t('page.parameters.save_version', { count: nextVersion })}
            </Button>
          </span>
        </div>
      )}

      <HistoryDrawer open={historyOpen} onClose={() => setHistoryOpen(false)} />
    </Form>
  );
}

function formatPercent(value: number): string {
  const text = new Intl.NumberFormat('pl-PL', {
    minimumFractionDigits: 1,
    maximumFractionDigits: 1,
  }).format(Math.abs(value));

  return `${value > 0 ? '+' : value < 0 ? '−' : ''}${text} %`;
}

function Row({
  parameter,
  dirty,
  impact,
  t,
}: {
  parameter: Parameter;
  dirty: boolean;
  impact: { percent: number | null; sample: string | null } | null;
  t: (key: string, options?: Record<string, unknown>) => string;
}) {
  const { formState } = useCurrentForm();
  const unit =
    parameterUnits[parameter.key] ?? (parameter.type === 'percent' ? '%' : '');
  const numeric = parameter.type === 'number' || parameter.type === 'percent';
  const error = formState.errors[parameter.key];
  const message = typeof error?.message === 'string' ? error.message : null;

  return (
    <div className={dirty ? 'ge-params__row is-dirty' : 'ge-params__row'}>
      <div>
        <div className="ge-name">
          {t(`page.parameters.field.${parameter.key}`)}
        </div>
        <div className="ge-note">{parameter.description ?? ''}</div>
      </div>

      <div className="ge-params__value">
        {parameter.type === 'choice' ? (
          <FormControl
            variant="select"
            name={parameter.key}
            options={parameter.options.map((option) => ({
              label: t(`page.parameters.option.${option}`),
              value: option,
            }))}
          />
        ) : (
          <>
            <FormControl
              variant={numeric ? 'number' : 'text'}
              name={parameter.key}
            />
            {unit && <span className="ge-params__unit">{unit}</span>}
          </>
        )}
        {message !== null && <div className="ge-params__error">{message}</div>}
      </div>

      <div className="ge-note">
        {t(`page.parameters.applies.${parameter.key}`)}
      </div>

      <div>
        {impact === null ? (
          <span className="ge-note">—</span>
        ) : impact.percent === null ? (
          <span className="ge-note">
            {t('page.parameters.no_price_impact')}
          </span>
        ) : (
          <>
            <div
              className={
                impact.percent >= 0 ? 'ge-params__up' : 'ge-params__down'
              }
            >
              {formatPercent(impact.percent)}
            </div>
            <div className="ge-note">{impact.sample}</div>
          </>
        )}
      </div>

      <div>
        {dirty ? (
          <>
            <div className="ge-params__unsaved">
              {t('page.parameters.dirty')}
            </div>
            <div className="ge-note">
              {t('page.parameters.was', { value: parameter.value ?? '—' })}
            </div>
          </>
        ) : (
          <div className="ge-note">
            {[parameter.changed_at, parameter.changed_by]
              .filter(Boolean)
              .join(' · ')}
          </div>
        )}
      </div>
    </div>
  );
}
