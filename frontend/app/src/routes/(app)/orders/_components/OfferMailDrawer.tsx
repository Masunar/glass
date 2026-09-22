import { useEffect, useState } from 'react';

import { Button } from '@salvon/components/button';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { notifyError, notifySuccess } from '@salvon/utils/notify';

import type { OfferMailPreview, OfferRow } from '@app/api/OffersApi';
import { OffersApi } from '@app/api/OffersApi';
import Drawer, { DrawerColumn } from '@app/components/drawer/Drawer';

type Props = {
  orderId: number;
  offer: OfferRow | null;
  open: boolean;
  onClose: () => void;
  onSent: () => void;
};

/**
 * Wysyłka oferty mailem.
 *
 * Treść przychodzi gotowa ze słownika szablonów, z podstawionym numerem
 * i terminem ważności — ale jest **edytowalna przed wysłaniem**, bo po
 * rozmowie telefonicznej zwykle chce się dopisać zdanie.
 *
 * Adres podpowiada się z kartoteki i też da się go poprawić: oferta
 * często idzie do konkretnej osoby, nie na skrzynkę firmową. Użyty
 * adres ląduje w dzienniku zlecenia — za pół roku pytanie brzmi „gdzie
 * to poszło", a nie „czy poszło".
 */
export default function OfferMailDrawer({
  orderId,
  offer,
  open,
  onClose,
  onSent,
}: Props) {
  const t = useTranslation();
  const [preview, setPreview] = useState<OfferMailPreview | null>(null);
  const [to, setTo] = useState('');
  const [subject, setSubject] = useState('');
  const [body, setBody] = useState('');
  const [busy, setBusy] = useState(false);
  const [errors, setErrors] = useState<Record<string, string[]>>({});

  useEffect(() => {
    if (!open || offer === null) {
      return;
    }

    setPreview(null);
    setErrors({});

    void (async () => {
      const { content } = await OffersApi.mailPreview(orderId, offer.id);
      const data: OfferMailPreview | undefined = content?.data;

      if (data) {
        setPreview(data);
        setTo(data.to ?? '');
        setSubject(data.subject);
        setBody(data.body);
      }
    })();
  }, [open, offer?.id]);

  const send = async () => {
    if (offer === null) {
      return;
    }

    setBusy(true);
    setErrors({});

    const { content, response } = await OffersApi.send(orderId, offer.id, {
      to,
      subject,
      body,
    });

    setBusy(false);

    if (!response.success) {
      setErrors(content?.errors ?? {});
      notifyError(
        content?.errors?.offer?.[0] ??
          content?.errors?.to?.[0] ??
          t('api.ise'),
      );

      return;
    }

    notifySuccess(t('page.orders.offers.sent_to', { address: to }));
    onSent();
  };

  return (
    <Drawer
      open={open}
      onClose={onClose}
      kicker={t('page.orders.offers.kicker')}
      title={t('page.orders.offers.send_title', {
        number: offer?.number ?? '',
      })}
      foot={
        <div className="ge-drawer__foot-end">
          <Button variant="text" onClick={onClose}>
            {t('cancel')}
          </Button>
          <Button
            variant="contained"
            loading={busy}
            disabled={preview === null}
            onClick={() => void send()}
          >
            {t('page.orders.offers.send')}
          </Button>
        </div>
      }
    >
      <DrawerColumn>
        {preview === null ? (
          <div className="ge-quiet">{t('page.orders.card.loading')}</div>
        ) : (
          <>
            <label className="ge-uf">
              <span className="ge-uf__label">
                {t('page.orders.offers.mail_to')}
              </span>
              <input
                className="ge-uf__input"
                value={to}
                placeholder={t('page.orders.offers.mail_to_hint')}
                onChange={(event) => setTo(event.target.value)}
              />
              <span className="ge-uf__hint">
                {preview.to === null
                  ? t('page.orders.offers.mail_to_missing')
                  : t('page.orders.offers.mail_to_note')}
              </span>
              {errors.to && <span className="ge-uf__error">{errors.to[0]}</span>}
            </label>

            <label className="ge-uf">
              <span className="ge-uf__label">
                {t('page.orders.offers.mail_subject')}
              </span>
              <input
                className="ge-uf__input"
                value={subject}
                onChange={(event) => setSubject(event.target.value)}
              />
              {errors.subject && (
                <span className="ge-uf__error">{errors.subject[0]}</span>
              )}
            </label>

            <label className="ge-uf">
              <span className="ge-uf__label">
                {t('page.orders.offers.mail_body')}
              </span>
              <textarea
                className="ge-uf__input ge-mail__body"
                rows={12}
                value={body}
                onChange={(event) => setBody(event.target.value)}
              />
              <span className="ge-uf__hint">
                {t('page.orders.offers.mail_body_note')}
              </span>
              {errors.body && (
                <span className="ge-uf__error">{errors.body[0]}</span>
              )}
            </label>

            <div className="ge-mail__meta">
              <div className="ge-kv">
                <span className="ge-kv__k">
                  {t('page.orders.offers.mail_attachment')}
                </span>
                <span>{preview.attachment}</span>
              </div>
              <div className="ge-kv">
                <span className="ge-kv__k">
                  {t('page.orders.offers.mail_reply_to')}
                </span>
                <span>
                  {preview.reply_to ?? t('page.orders.offers.mail_no_reply_to')}
                </span>
              </div>
            </div>
          </>
        )}
      </DrawerColumn>
    </Drawer>
  );
}
