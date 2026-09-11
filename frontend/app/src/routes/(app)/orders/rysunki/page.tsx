import OrderTabs from '../_components/OrderTabs';
import { useEffect, useState } from 'react';
import { PiFilePdf, PiImage, PiTrash } from 'react-icons/pi';
import { useParams } from 'react-router';

import { Button } from '@salvon/components/button';
import { FileUpload } from '@salvon/components/file-upload';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { notifyError, notifySuccess } from '@salvon/utils/notify';

import type { OrderDrawingRow, OrderDrawingsBoard } from '@app/api/OrdersApi';
import { OrdersApi } from '@app/api/OrdersApi';

const size = (bytes: number) =>
  bytes >= 1024 * 1024
    ? `${(bytes / 1024 / 1024).toFixed(1)} MB`
    : `${Math.max(1, Math.round(bytes / 1024))} kB`;

export default function Page() {
  const t = useTranslation();
  const params = useParams();
  const id = Number(params.id);

  const [board, setBoard] = useState<OrderDrawingsBoard | null>(null);
  const [itemId, setItemId] = useState<string>('');
  const [note, setNote] = useState('');
  const [busy, setBusy] = useState(false);

  const load = async () => {
    const { content } = await OrdersApi.drawings(id);
    const data: OrderDrawingsBoard | undefined = content?.data;

    if (data) {
      setBoard(data);
    }
  };

  useEffect(() => {
    void load();
  }, [id]);

  const upload = async (files: File[]) => {
    setBusy(true);

    for (const file of files) {
      const { content, response } = await OrdersApi.addDrawing(
        id,
        file,
        itemId === '' ? null : Number(itemId),
        note,
      );

      if (!response.success) {
        notifyError(content?.errors?.file?.[0] ?? t('api.ise'));
        setBusy(false);
        await load();

        return;
      }
    }

    setBusy(false);
    setNote('');
    notifySuccess(t('api.save_success'));
    await load();
  };

  const remove = async (row: OrderDrawingRow) => {
    const { response } = await OrdersApi.deleteDrawing(id, row.id);

    if (!response.success) {
      notifyError(t('api.ise'));

      return;
    }

    await load();
  };

  const declare = async (complete: boolean) => {
    setBusy(true);
    const { response } = await OrdersApi.declareDrawings(id, complete);
    setBusy(false);

    if (!response.success) {
      notifyError(t('api.ise'));

      return;
    }

    await load();
  };

  if (!board) {
    return <div className="ge-empty">{t('page.orders.card.loading')}</div>;
  }

  const complete = board.complete;

  return (
    <>
      <header className="ge-head">
        <div>
          <div className="ge-head__kicker">
            {t('page.orders.drawings.title')} · #{board.order.number}
          </div>
          <h1 className="ge-head__title">
            {t('page.orders.drawings.count', { count: board.drawings.length })}
          </h1>
        </div>
      </header>

      <OrderTabs orderId={id} active="drawings" counts={board.tabs} />

      <div className="ge-card">
        <div className="ge-card__main">
          <section className="ge-section">
            <div className="ge-section__head ge-section__head--strong">
              {t('page.orders.drawings.add')}
              <span className="ge-section__end ge-quiet">
                {t('page.orders.drawings.formats', {
                  list: board.accepts.join(', '),
                  mb: Math.round(board.max_kilobytes / 1024),
                })}
              </span>
            </div>

            <div className="ge-upload__meta">
              <label className="ge-uf">
                <span className="ge-uf__label">
                  {t('page.orders.drawings.item')}
                </span>
                <select
                  className="ge-uf__input ge-uf__select"
                  value={itemId}
                  onChange={(event) => setItemId(event.target.value)}
                >
                  <option value="">
                    {t('page.orders.drawings.whole_order')}
                  </option>
                  {board.items.map((item) => (
                    <option key={item.id} value={item.id}>
                      {item.name}
                    </option>
                  ))}
                </select>
              </label>

              <label className="ge-uf">
                <span className="ge-uf__label">
                  {t('page.orders.drawings.note')}
                </span>
                <input
                  className="ge-uf__input"
                  value={note}
                  placeholder={t('page.orders.drawings.note_hint')}
                  onChange={(event) => setNote(event.target.value)}
                />
              </label>
            </div>

            <FileUpload
              multiple
              disabled={busy}
              showUploadedList={false}
              maxSize={board.max_kilobytes * 1024}
              accept={board.accepts.map((extension) => `.${extension}`)}
              onChange={() => {}}
              onDropAccepted={(files) => void upload(files)}
              onDropRejected={() =>
                notifyError(t('page.orders.drawings.rejected'))
              }
            />
          </section>

          <section className="ge-section">
            <div className="ge-section__head ge-section__head--strong">
              {t('page.orders.drawings.list')}
            </div>

            {board.drawings.length === 0 && (
              <div className="ge-quiet">{t('page.orders.drawings.empty')}</div>
            )}

            {board.drawings.map((row) => (
              <div className="ge-draw" key={row.id}>
                <span className="ge-draw__icon">
                  {row.is_image ? <PiImage /> : <PiFilePdf />}
                </span>

                <span className="ge-draw__name">
                  <a
                    href={OrdersApi.drawingUrl(id, row.id)}
                    target="_blank"
                    rel="noreferrer"
                  >
                    {row.name}
                  </a>
                  <span className="ge-quiet">
                    {row.item_name ?? t('page.orders.drawings.whole_order')}
                    {row.note ? ` · ${row.note}` : ''}
                  </span>
                </span>

                <span className="ge-quiet">{size(row.size_bytes)}</span>

                <span className="ge-quiet">
                  {row.uploaded_at}
                  {row.uploaded_by ? ` · ${row.uploaded_by}` : ''}
                </span>

                <button
                  type="button"
                  className="ge-draw__remove"
                  aria-label={t('delete')}
                  onClick={() => void remove(row)}
                >
                  <PiTrash />
                </button>
              </div>
            ))}
          </section>
        </div>

        <aside className="ge-card__side ge-card__side--right">
          <section className="ge-section">
            <div className="ge-section__head">
              {t('page.orders.drawings.declaration')}
            </div>

            <div className="ge-section__body">
              {complete.declared ? (
                <>
                  <div className="ge-draw__declared">
                    {t('page.orders.drawings.declared_by', {
                      who: complete.by ?? '—',
                      when: complete.at ?? '—',
                    })}
                  </div>
                  <Button
                    variant="outlined"
                    size="small"
                    disabled={busy}
                    onClick={() => void declare(false)}
                  >
                    {t('page.orders.drawings.withdraw')}
                  </Button>
                </>
              ) : (
                <>
                  <div className="ge-quiet">
                    {t('page.orders.drawings.declaration_note')}
                  </div>
                  <Button
                    variant="contained"
                    size="small"
                    disabled={busy}
                    onClick={() => void declare(true)}
                  >
                    {t('page.orders.drawings.declare')}
                  </Button>
                </>
              )}
            </div>
          </section>

          <section className="ge-section">
            <div className="ge-quiet">
              {t('page.orders.drawings.withdraw_note')}
            </div>
          </section>
        </aside>
      </div>
    </>
  );
}
