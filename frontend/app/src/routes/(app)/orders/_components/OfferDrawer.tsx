import { useEffect, useState } from 'react';

import { Button } from '@salvon/components/button';
import { Form } from '@salvon/components/form';
import { useForm } from '@salvon/hooks/useForm';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { validationCompleted } from '@salvon/utils/api-validation';
import { notifyError, notifySuccess } from '@salvon/utils/notify';

import type { OrderOffersBoard } from '@app/api/OffersApi';
import { OffersApi } from '@app/api/OffersApi';
import Drawer, { DrawerColumn } from '@app/components/drawer/Drawer';
import Field, { Choice } from '@app/components/drawer/Field';
import Fieldset, { FieldNote } from '@app/components/drawer/Fieldset';

type Props = {
  orderId: number;
  current: OrderOffersBoard['current'];
  open: boolean;
  onClose: () => void;
  onSaved: () => void;
};

/**
 * Wystawienie oferty.
 *
 * Trzy decyzje i komentarz. Wszystkie trzy mają domyślne wartości
 * dobrane tak, żeby najczęstszy przypadek nie wymagał ani jednego
 * kliknięcia: nieszczegółowa (Z-Ż-05), suma pomijająca warianty
 * (Z-Ż-06), netto.
 *
 * Po zapisie oferty nie da się już zmienić — poprawka to następne
 * wystawienie. Dlatego panel mówi o tym wprost, zanim ktoś kliknie.
 */
export default function OfferDrawer({
  orderId,
  current,
  open,
  onClose,
  onSaved,
}: Props) {
  const t = useTranslation();
  const form = useForm();
  const [saving, setSaving] = useState(false);
  const [display, setDisplay] = useState('net');

  useEffect(() => {
    if (!open) {
      return;
    }

    setDisplay('net');
    form.reset({
      detail_level: 'summary',
      sum_mode: 'components',
      price_display: 'net',
      comment: '',
    });
  }, [open]);

  const submit = async (data: any) => {
    setSaving(true);
    const { content, response } = await OffersApi.issue(orderId, data);
    setSaving(false);

    if (!validationCompleted(content, form.setError, t)) {
      return;
    }

    if (!response.success) {
      notifyError(content?.errors?.offer?.[0] ?? t('api.ise'));

      return;
    }

    notifySuccess(
      t('page.orders.offers.issued', { number: content?.data?.number ?? '' }),
    );
    onSaved();
  };

  /** Brutto bez znanej stawki nie istnieje — mówimy to przed zapisem. */
  const grossBlocked = display === 'gross' && current.gross === null;

  return (
    <Drawer
      open={open}
      onClose={onClose}
      narrow
      kicker={t('page.orders.offers.kicker')}
      title={t('page.orders.offers.issue')}
      foot={
        <div className="ge-drawer__foot-end">
          <Button variant="text" onClick={onClose}>
            {t('cancel')}
          </Button>
          <Button
            variant="contained"
            loading={saving}
            disabled={grossBlocked}
            onClick={() => void form.handleSubmit(submit)()}
          >
            {t('page.orders.offers.issue')}
          </Button>
        </div>
      }
    >
      <Form form={form} onSubmit={submit}>
        <DrawerColumn>
          <Fieldset tone="terms" label={t('page.orders.offers.what_shows')}>
            <Choice
              name="detail_level"
              label={t('page.orders.offers.detail')}
              options={[
                {
                  value: 'summary',
                  label: t('page.orders.offers.detail_summary_long'),
                },
                {
                  value: 'detailed',
                  label: t('page.orders.offers.detail_detailed_long'),
                },
              ]}
            />
            <FieldNote>{t('page.orders.offers.detail_note')}</FieldNote>

            <Choice
              name="price_display"
              label={t('page.orders.offers.display')}
              onChange={(value: string) => setDisplay(value)}
              options={[
                { value: 'net', label: t('page.orders.offers.display_net') },
                {
                  value: 'gross',
                  label: t('page.orders.offers.display_gross'),
                },
              ]}
            />
            {grossBlocked ? (
              <div className="ge-note ge-note--warn">
                {current.unknown_reason ??
                  t('page.orders.offers.gross_blocked')}
              </div>
            ) : (
              <FieldNote>{t('page.orders.offers.display_note')}</FieldNote>
            )}
          </Fieldset>

          <Fieldset tone="addr" label={t('page.orders.offers.what_sums')}>
            <Choice
              name="sum_mode"
              label={t('page.orders.offers.sum')}
              options={[
                {
                  value: 'components',
                  label: t('page.orders.offers.sum_components'),
                },
                { value: 'all', label: t('page.orders.offers.sum_all') },
                { value: 'none', label: t('page.orders.offers.sum_none') },
              ]}
            />
            <FieldNote>{t('page.orders.offers.sum_note')}</FieldNote>
          </Fieldset>

          <Fieldset tone="contact" label={t('page.orders.offers.note_section')}>
            <Field
              name="comment"
              label={t('page.orders.offers.comment')}
              placeholder={t('page.orders.offers.comment_hint')}
            />
            <FieldNote>{t('page.orders.offers.frozen')}</FieldNote>
          </Fieldset>
        </DrawerColumn>
      </Form>
    </Drawer>
  );
}
