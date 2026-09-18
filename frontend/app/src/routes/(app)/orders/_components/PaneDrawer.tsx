import { useEffect, useState } from 'react';

import { Button } from '@salvon/components/button';
import { Form } from '@salvon/components/form';
import { useForm } from '@salvon/hooks/useForm';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { validationCompleted } from '@salvon/utils/api-validation';
import { notifyError, notifySuccess } from '@salvon/utils/notify';

import type {
  OrderItemsBoard,
  OrderPaneRow,
  OrderProcessItem,
  PanePreview,
} from '@app/api/OrdersApi';
import { OrdersApi } from '@app/api/OrdersApi';
import Drawer, { DrawerColumn } from '@app/components/drawer/Drawer';
import Field, { Choice, Toggle } from '@app/components/drawer/Field';
import Fieldset, { FieldNote, FieldRow } from '@app/components/drawer/Fieldset';

type Props = {
  orderId: number;
  board: OrderItemsBoard;
  item: OrderPaneRow | null;
  open: boolean;
  onClose: () => void;
  onSaved: () => void;
};

const empty = {
  order_list_id: '',
  product_id: '',
  width_mm: '',
  height_mm: '',
  quantity: 1,
  is_irregular_shape: false,
  is_tempered: false,
  needs_mark: false,
  is_urgent: false,
  min_billable_m2: '',
  note: '',
  production_note: '',
};

/** Etap na formatce — wybrana pozycja cennikowa, dni, cena, uwaga. */
type Step = {
  key: string;
  process_id: number;
  product_id: string;
  days: string;
  unit_net_price: string;
  comment: string;
};

let counter = 0;

const nextKey = () => `s${++counter}`;

/**
 * Pozycje cennikowe procesu dostępne dla tej grubości szkła.
 *
 * Grubość **zawęża** listę, ale jej nie rozstrzyga: fazowanie ma dla
 * ośmiomilimetrowej szyby osiem wierszy (faza 5…40 mm), a CNC cztery
 * w ogóle od grubości niezależne — te mają w słowniku grubość pustą
 * i wchodzą dopiero wtedy, gdy nic dopasowanego nie ma.
 */
const candidates = (
  items: OrderProcessItem[],
  thickness: number | null,
): OrderProcessItem[] => {
  const matched =
    thickness === null
      ? []
      : items.filter((row) => row.glass_thickness_mm === thickness);

  return matched.length > 0
    ? matched
    : items.filter((row) => row.glass_thickness_mm === null);
};

/**
 * Formatka: materiał, wymiary i procesy.
 *
 * Cena materiału nie jest polem formularza — liczy ją serwer przy
 * zapisie i zapisuje razem ze ścieżką wyliczenia. Gdyby dało się ją tu
 * wpisać, ścieżka przestałaby cokolwiek znaczyć.
 *
 * Cena etapu jest polem, bo w cenniku bywa jej po prostu brak, a robota
 * i tak kosztuje. Puste pole znaczy „weź z cennika", nie „zero".
 */
export default function PaneDrawer({
  orderId,
  board,
  item,
  open,
  onClose,
  onSaved,
}: Props) {
  const t = useTranslation();
  const form = useForm();
  const [steps, setSteps] = useState<Step[]>([]);
  const [saving, setSaving] = useState(false);
  const [preview, setPreview] = useState<PanePreview | null>(null);

  useEffect(() => {
    if (!open) {
      return;
    }

    setSteps(
      inRouteOrder(
        item?.processes.map((entry) => ({
          key: nextKey(),
          process_id: entry.process_id,
          product_id: entry.product_id === null ? '' : String(entry.product_id),
          days: entry.days === null ? '' : String(entry.days),
          unit_net_price: entry.unit_net_price,
          comment: entry.comment ?? '',
        })) ?? [],
      ),
    );

    form.reset({
      ...empty,
      order_list_id: board.lists[0]?.id ?? '',
      product_id: item?.product_id ?? '',
      width_mm: item?.width_mm ?? '',
      height_mm: item?.height_mm ?? '',
      quantity: Number(item?.quantity ?? 1),
      is_irregular_shape: item?.is_irregular_shape ?? false,
      is_tempered: item?.is_tempered ?? false,
      needs_mark: item?.needs_mark ?? false,
      is_urgent: item?.is_urgent ?? false,
      // Puste pole znaczy „z parametrow wyceny" — podstawiamy tylko
      // wpisany wyjatek, nie wartosc globalna, zeby jej przypadkiem
      // nie utrwalic przy zapisie.
      min_billable_m2:
        item?.min_billable_m2 === null || item?.min_billable_m2 === undefined
          ? ''
          : String(item.min_billable_m2),
      note: item?.note ?? '',
      production_note: item?.production_note ?? '',
    });
  }, [open, item?.id]);

  const materialId = form.watch('product_id');
  const thickness =
    board.catalogue.products.find(
      (row) => String(row.id) === String(materialId),
    )?.thickness_mm ?? null;

  /** Domyślny wybór tylko przy jednym kandydacie — inaczej czeka człowiek. */
  const add = (processId: number) => {
    const process = board.catalogue.processes.find(
      (row) => row.id === processId,
    );
    const only = candidates(process?.items ?? [], thickness);

    setSteps((current) =>
      inRouteOrder([
        ...current,
        {
          key: nextKey(),
          process_id: processId,
          product_id: only.length === 1 ? String(only[0].product_id) : '',
          days:
            process?.duration_days === null ||
            process?.duration_days === undefined
              ? ''
              : String(process.duration_days),
          unit_net_price: '',
          comment: '',
        },
      ]),
    );
  };

  const toggle = (processId: number) => {
    const used = steps.some((step) => step.process_id === processId);

    if (used) {
      setSteps((current) =>
        current.filter((step) => step.process_id !== processId),
      );

      return;
    }

    add(processId);
  };

  /**
   * Etapy trzymane w kolejności marszruty, a te same procesy obok
   * siebie. Dołożony szlif ma stanąć przy szlifie, a nie na końcu za
   * hartownią — inaczej lista przestaje przypominać drogę, którą szyba
   * naprawdę przejdzie.
   *
   * Sortowanie jest stabilne, więc kolejność dwóch faz między sobą
   * zostaje taka, w jakiej je dołożono.
   */
  const inRouteOrder = (rows: Step[]): Step[] => {
    const rank = (id: number) => {
      const index = board.catalogue.processes.findIndex((row) => row.id === id);

      return index === -1 ? Number.MAX_SAFE_INTEGER : index;
    };

    return [...rows].sort((a, b) => rank(a.process_id) - rank(b.process_id));
  };

  const patch = (key: string, change: Partial<Step>) =>
    setSteps((current) =>
      current.map((step) => (step.key === key ? { ...step, ...change } : step)),
    );

  const drop = (key: string) =>
    setSteps((current) => current.filter((step) => step.key !== key));

  const totalDays = steps.reduce(
    (sum, step) => sum + (step.days === '' ? 0 : Number(step.days)),
    0,
  );

  /**
   * Ładunek formularza — jedno miejsce dla zapisu i dla podglądu.
   * Gdyby podgląd budował go po swojemu, prędzej czy później pokazałby
   * kwotę, której zapis nie potwierdzi.
   */
  const payload = (data: any) => ({
    ...data,
    min_billable_m2: data.min_billable_m2 === '' ? null : data.min_billable_m2,
    note: data.note === '' ? null : data.note,
    production_note: data.production_note === '' ? null : data.production_note,
    processes: steps.map((step) => ({
      process_id: step.process_id,
      product_id: step.product_id === '' ? null : Number(step.product_id),
      days: step.days === '' ? null : Number(step.days),
      unit_net_price: step.unit_net_price === '' ? null : step.unit_net_price,
      comment: step.comment === '' ? null : step.comment,
    })),
  });

  const watched = form.watch();

  // Podglad na zywo. Opozniony, bo inaczej kazde wcisniecie klawisza
  // w polu szerokosci to osobne zapytanie.
  useEffect(() => {
    if (!open) {
      return;
    }

    const timer = setTimeout(() => {
      void (async () => {
        const { content } = await OrdersApi.previewPane(
          orderId,
          payload(form.getValues()),
        );
        const data: PanePreview | undefined = content?.data;

        if (data) {
          setPreview(data);
        }
      })();
    }, 350);

    return () => clearTimeout(timer);
  }, [open, JSON.stringify(watched), JSON.stringify(steps)]);

  const submit = async (data: any) => {
    setSaving(true);

    const { content, response } = await OrdersApi.savePane(
      orderId,
      payload(data),
      item?.id,
    );

    setSaving(false);

    if (!validationCompleted(content, form.setError, t)) {
      return;
    }

    if (!response.success) {
      notifyError(t('api.ise'));

      return;
    }

    notifySuccess(t('api.save_success'));
    onSaved();
  };

  return (
    <Drawer
      open={open}
      onClose={onClose}
      narrow
      wideForm
      kicker={t('page.orders.panes.title')}
      title={t(item ? 'page.orders.panes.edit' : 'page.orders.panes.add')}
      foot={
        <>
          <span className="ge-drawer__foot-note">
            {preview?.ready && preview.total !== null
              ? t('page.orders.panes.foot_total', { amount: preview.total })
              : t('page.orders.panes.price_note')}
          </span>
          <div className="ge-drawer__foot-end">
            <Button variant="text" onClick={onClose}>
              {t('cancel')}
            </Button>
            <Button
              variant="contained"
              loading={saving}
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
          <Fieldset
            tone="ident"
            label={t('page.orders.panes.section.material')}
          >
            <Choice
              name="product_id"
              label={t('page.orders.panes.material')}
              required
              emptyLabel={t('page.orders.panes.material_pick')}
              options={board.catalogue.products.map((product) => ({
                value: product.id,
                label: product.group
                  ? `${product.group} · ${product.name}`
                  : product.name,
              }))}
            />

            {board.lists.length > 1 && (
              <Choice
                name="order_list_id"
                label={t('page.orders.panes.list')}
                options={board.lists.map((list) => ({
                  value: list.id,
                  label:
                    list.name ??
                    t('page.orders.card.list', { number: list.number }),
                }))}
              />
            )}
          </Fieldset>

          <Fieldset tone="addr" label={t('page.orders.panes.section.size')}>
            <FieldRow columns="1fr 1fr 1fr">
              <Field
                name="width_mm"
                label={t('page.orders.panes.width')}
                required
                emphasis="num"
                type="number"
              />
              <Field
                name="height_mm"
                label={t('page.orders.panes.height')}
                required
                emphasis="num"
                type="number"
              />
              <Field
                name="quantity"
                label={t('page.orders.panes.quantity')}
                emphasis="num"
                type="number"
              />
            </FieldRow>

            <FieldRow columns="1fr" paddingTop={10}>
              <Toggle
                name="is_tempered"
                label={t('page.orders.panes.tempered')}
              />
              <Toggle
                name="is_irregular_shape"
                label={t('page.orders.panes.irregular')}
              />
              <Toggle name="needs_mark" label={t('page.orders.panes.mark')} />
              <Toggle name="is_urgent" label={t('page.orders.panes.urgent')} />
            </FieldRow>

            <FieldNote>{t('page.orders.panes.size_note')}</FieldNote>

            {/* Nadpisanie parametru wyceny dla tej jednej formatki.
                Puste pole nie znaczy zero — znaczy „obowiazuje wartosc
                ze slownika", ktora zalezy od hartowania. */}
            <Field
              name="min_billable_m2"
              label={t('page.orders.panes.min_billable')}
              placeholder={t('page.orders.panes.min_billable_hint')}
            />
            <FieldNote>{t('page.orders.panes.min_billable_note')}</FieldNote>
          </Fieldset>

          <Fieldset
            tone="terms"
            label={t('page.orders.panes.section.processes')}
          >
            <div className="ge-chips">
              {board.catalogue.processes.map((process) => (
                <button
                  key={process.id}
                  type="button"
                  className={
                    steps.some((step) => step.process_id === process.id)
                      ? 'ge-chip is-on'
                      : 'ge-chip'
                  }
                  onClick={() => toggle(process.id)}
                >
                  {process.name}
                </button>
              ))}
            </div>

            {steps.map((step) => {
              const process = board.catalogue.processes.find(
                (row) => row.id === step.process_id,
              );
              const options = candidates(process?.items ?? [], thickness);
              const needsChoice =
                step.product_id === '' && options.length !== 1;

              return (
                <div className="ge-proc" key={step.key}>
                  <div className="ge-proc__head">
                    <span className="ge-proc__name">
                      {process?.name ?? '—'}
                    </span>
                    <button
                      type="button"
                      className="ge-proc__add"
                      title={t('page.orders.panes.step_again')}
                      onClick={() => add(step.process_id)}
                    >
                      +
                    </button>
                    <button
                      type="button"
                      className="ge-proc__drop"
                      title={t('delete')}
                      onClick={() => drop(step.key)}
                    >
                      ×
                    </button>
                  </div>

                  {/* Automat wybiera tylko przy jednym kandydacie. Przy
                      kilku — faza 5…40 mm dla tej samej grubosci — pole
                      czeka, bo zgadniety wariant to zla cena w ofercie. */}
                  <select
                    className={
                      needsChoice
                        ? 'ge-uf__input ge-uf__select is-empty'
                        : 'ge-uf__input ge-uf__select'
                    }
                    value={step.product_id}
                    onChange={(event) =>
                      patch(step.key, { product_id: event.target.value })
                    }
                  >
                    <option value="">
                      {options.length === 0
                        ? t('page.orders.panes.step_no_items')
                        : t('page.orders.panes.step_pick')}
                    </option>
                    {options.map((row) => (
                      <option key={row.product_id} value={row.product_id}>
                        {row.name}
                      </option>
                    ))}
                  </select>

                  <div className="ge-proc__row">
                    <label className="ge-uf">
                      <span className="ge-uf__label">
                        {t('page.orders.panes.step_days')}
                      </span>
                      <input
                        className="ge-uf__input"
                        inputMode="numeric"
                        value={step.days}
                        onChange={(event) =>
                          patch(step.key, { days: event.target.value })
                        }
                      />
                    </label>

                    <label className="ge-uf">
                      <span className="ge-uf__label">
                        {t('page.orders.panes.step_price')}
                      </span>
                      <input
                        className="ge-uf__input"
                        inputMode="decimal"
                        placeholder={t('page.orders.panes.step_price_hint')}
                        value={step.unit_net_price}
                        onChange={(event) =>
                          patch(step.key, {
                            unit_net_price: event.target.value,
                          })
                        }
                      />
                    </label>
                  </div>

                  <label className="ge-uf">
                    <span className="ge-uf__label">
                      {t('page.orders.panes.step_comment')}
                    </span>
                    <input
                      className="ge-uf__input"
                      value={step.comment}
                      onChange={(event) =>
                        patch(step.key, { comment: event.target.value })
                      }
                    />
                  </label>
                </div>
              );
            })}

            {steps.length > 0 && (
              <FieldNote>
                {t('page.orders.panes.step_total_days', { count: totalDays })}
              </FieldNote>
            )}

            {/* Podsumowanie z formula przy kazdej pozycji. Kwota bez
                pokazanego mnozenia to liczba bez pochodzenia — a przy
                procesach jednostka bywa rozna: ciecie idzie od metra
                biezacego, hartownia od metra kwadratowego, CNC od sztuki. */}
            {preview?.ready && (
              <div className="ge-calc">
                <div className="ge-calc__row">
                  <span>{t('page.orders.panes.calc_material')}</span>
                  <span className="ge-calc__formula">
                    {preview.m2} m² ×{' '}
                    {preview.net_price_per_square_meter ?? '—'} zł/m²
                  </span>
                  <span className="ge-calc__amount">
                    {preview.glass_net ?? '—'}
                  </span>
                </div>

                {preview.processes.map((row, index) => (
                  <div
                    className={
                      row.unavailable === null
                        ? 'ge-calc__row'
                        : 'ge-calc__row ge-calc__row--off'
                    }
                    key={`${row.process_id}-${index}`}
                  >
                    <span>
                      {row.label}
                      {row.parameter && (
                        <span className="ge-quiet"> · {row.parameter}</span>
                      )}
                    </span>
                    <span className="ge-calc__formula">
                      {row.unavailable === null
                        ? `${row.units} ${row.unit_label} × ${row.unit_net_price} zł/${row.unit_label}`
                        : t(`page.orders.panes.why.${row.unavailable}`)}
                    </span>
                    <span className="ge-calc__amount">{row.amount}</span>
                  </div>
                ))}

                <div className="ge-calc__row ge-calc__row--total">
                  <span>{t('page.orders.panes.calc_total')}</span>
                  <span className="ge-calc__formula" />
                  <span className="ge-calc__amount">
                    {preview.total ?? '—'}
                  </span>
                </div>
              </div>
            )}
            <FieldNote>{t('page.orders.panes.processes_note')}</FieldNote>
          </Fieldset>

          <Fieldset tone="contact" label={t('page.orders.panes.notes_section')}>
            {/* Dwa komentarze, bo maja dwoch odbiorcow. Scalone w jeden
                znaczylyby, ze instrukcja technologiczna trafia na oferte
                albo uwaga handlowa na hale. */}
            <Field
              name="note"
              label={t('page.orders.panes.note')}
              placeholder={t('page.orders.panes.note_hint')}
            />
            <Field
              name="production_note"
              label={t('page.orders.panes.production_note')}
              placeholder={t('page.orders.panes.production_note_hint')}
            />
          </Fieldset>

          {item && item.price_path.length > 0 && (
            <Fieldset
              tone="contact"
              label={t('page.orders.panes.section.path')}
            >
              {item.price_path.map((step, index) => (
                <div className="ge-kv" key={index}>
                  <span className="ge-kv__k">
                    {step.label}
                    {step.detail && (
                      <span className="ge-quiet"> — {step.detail}</span>
                    )}
                  </span>
                  <span>{step.value}</span>
                </div>
              ))}
            </Fieldset>
          )}
        </DrawerColumn>
      </Form>
    </Drawer>
  );
}
