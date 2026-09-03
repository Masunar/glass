import RowModal from './_components/RowModal';
import { useEffect, useMemo, useState } from 'react';
import { PiPencilSimple, PiPlus, PiProhibit } from 'react-icons/pi';

import { Button } from '@salvon/components/button';
import { Flex } from '@salvon/components/div';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { notifySuccess } from '@salvon/utils/notify';

import {
  DictionariesApi,
  type Dictionary,
  type DictionaryField,
  type DictionaryRow,
} from '@app/api/DictionariesApi';
import HasPermission from '@app/components/HasPermission';
import {
  type Column,
  DataList,
  ListHead,
  Row,
  Stage,
} from '@app/components/list';
import { Permission, SubPermission } from '@app/config/permission';

/** Szerokość kolumny wynika z typu pola, nie z nazwy — flagi są wąskie. */
function widthFor(field: DictionaryField): string {
  if (field.type === 'boolean') {
    return '110px';
  }

  if (field.type === 'integer' || field.type === 'decimal') {
    return '130px';
  }

  if (field.type === 'select' || field.type === 'reference') {
    return '160px';
  }

  return field.key === 'name' ? 'minmax(200px, 1fr)' : '160px';
}

function cellValue(row: DictionaryRow, field: DictionaryField): string {
  if (field.type === 'reference') {
    const label = row[`${field.key}_label`];

    return typeof label === 'string' && label !== '' ? label : '—';
  }

  if (field.type === 'select') {
    const option = field.options.find(
      (candidate) => candidate.value === String(row[field.key] ?? ''),
    );

    return option?.label ?? '—';
  }

  const value = row[field.key];

  return value === null || value === undefined || value === ''
    ? '—'
    : String(value);
}

/**
 * Słowniki proste — jeden ekran na wszystkie zakładki.
 *
 * Stary system miał piętnaście osobnych ekranów o piętnastu układach.
 * Tutaj układ generuje się z opisu pól, który przychodzi z backendu,
 * więc zakładki nie mogą się rozjechać między sobą.
 *
 * Rodzaje i typy szkła, okuć oraz innych świadomie tu nie leżą: to
 * kartoteka produktów i mieszka w Cenniku, razem z cenami. W starym
 * systemie były rozdzielone, przez co dodanie produktu i nadanie mu
 * ceny były dwoma niepowiązanymi krokami.
 */
export default function Page() {
  const t = useTranslation();
  const [dictionaries, setDictionaries] = useState<Dictionary[]>([]);
  const [slug, setSlug] = useState<string | null>(null);
  const [rows, setRows] = useState<DictionaryRow[]>([]);
  const [includeInactive, setIncludeInactive] = useState(false);
  const [modal, setModal] = useState<{
    open: boolean;
    row: DictionaryRow | null;
  }>({ open: false, row: null });
  /**
   * Nieudane wywołanie API pokazujemy wprost. Pusty ekran bez słowa
   * wyjaśnienia jest nie do odróżnienia od pustego słownika, a to dwie
   * zupełnie różne sytuacje.
   */
  const [failure, setFailure] = useState<string | null>(null);

  const dictionary = useMemo(
    () => dictionaries.find((item) => item.slug === slug) ?? null,
    [dictionaries, slug],
  );

  const listFields = useMemo(
    () => (dictionary?.fields ?? []).filter((field) => field.in_list),
    [dictionary],
  );

  const columns: Column[] = useMemo(
    () => [
      ...listFields.map((field) => ({
        label: field.label,
        width: widthFor(field),
      })),
      { labelKey: 'page.dictionaries.column.actions', width: '190px' },
    ],
    [listFields],
  );

  const loadRows = async (
    nextSlug: string,
    inactive: boolean = includeInactive,
  ) => {
    const { content, response } = await DictionariesApi.rows(
      nextSlug,
      inactive,
    );

    if (!response.success) {
      setFailure(content?.message ?? t('api.ise'));
      setRows([]);
      return;
    }

    setFailure(null);
    setRows(content?.data?.rows ?? []);
  };

  useEffect(() => {
    void (async () => {
      const { content, response } = await DictionariesApi.schema();

      if (!response.success) {
        setFailure(content?.message ?? t('api.ise'));
        return;
      }

      const loaded: Dictionary[] = content?.data?.dictionaries ?? [];

      setDictionaries(loaded);
      setFailure(loaded.length === 0 ? t('page.dictionaries.no_schema') : null);

      if (loaded.length > 0) {
        setSlug(loaded[0].slug);
        await loadRows(loaded[0].slug, false);
      }
    })();
  }, []);

  const deactivate = async (row: DictionaryRow) => {
    if (slug === null) {
      return;
    }

    await DictionariesApi.deactivate(slug, row.id);
    notifySuccess(t('page.dictionaries.deactivated'));
    await loadRows(slug);
  };

  return (
    <>
      <header className="ge-head">
        <div>
          <div className="ge-head__kicker">{t('page.module.adm')}</div>
          <h1 className="ge-head__title">{t('page.dictionaries.title')}</h1>
        </div>

        <div className="ge-head__actions">
          {dictionary && (
            <HasPermission
              permission={Permission.DICTIONARIES}
              sub={SubPermission.CREATE}
            >
              <Button
                variant="contained"
                icon={<PiPlus />}
                onClick={() => setModal({ open: true, row: null })}
              >
                {t('page.dictionaries.add')}
              </Button>
            </HasPermission>
          )}
        </div>
      </header>

      <nav className="ge-filters" aria-label={t('page.dictionaries.tabs')}>
        {dictionaries.map((item) => (
          <button
            key={item.slug}
            type="button"
            className={item.slug === slug ? 'is-active' : ''}
            onClick={() => {
              setSlug(item.slug);
              void loadRows(item.slug);
            }}
          >
            {item.label}
          </button>
        ))}
        <span className="ge-filters__end">
          <button
            type="button"
            className={includeInactive ? 'is-active' : ''}
            onClick={() => {
              const next = !includeInactive;

              setIncludeInactive(next);

              if (slug !== null) {
                void loadRows(slug, next);
              }
            }}
          >
            {t('page.dictionaries.show_inactive')}
          </button>
        </span>
      </nav>

      {failure !== null && <p className="ge-lead ge-lead--warn">{failure}</p>}

      {dictionary?.note && <p className="ge-lead">{dictionary.note}</p>}

      {dictionary && (
        <DataList columns={columns}>
          <ListHead columns={columns} translate={t} />

          {rows.map((row) => (
            <Row key={row.id}>
              {listFields.map((field) =>
                field.type === 'boolean' ? (
                  <Stage
                    key={field.key}
                    label={row[field.key] === true ? t('yes') : t('no')}
                    tone={row[field.key] === true ? 'done' : 'idle'}
                  />
                ) : (
                  <div key={field.key}>
                    {field.key === 'name' ? (
                      <div className="ge-name">{cellValue(row, field)}</div>
                    ) : (
                      cellValue(row, field)
                    )}
                  </div>
                ),
              )}

              <Flex gap={1}>
                <HasPermission
                  permission={Permission.DICTIONARIES}
                  sub={SubPermission.UPDATE}
                >
                  <Button
                    variant="text"
                    size="small"
                    icon={<PiPencilSimple />}
                    onClick={() => setModal({ open: true, row })}
                  >
                    {t('edit')}
                  </Button>
                </HasPermission>

                {row.is_active === true && (
                  <HasPermission
                    permission={Permission.DICTIONARIES}
                    sub={SubPermission.UPDATE}
                  >
                    <Button
                      variant="text"
                      size="small"
                      icon={<PiProhibit />}
                      onClick={() => void deactivate(row)}
                    >
                      {t('page.dictionaries.deactivate')}
                    </Button>
                  </HasPermission>
                )}
              </Flex>
            </Row>
          ))}

          {rows.length === 0 && (
            <div className="ge-empty">{t('page.dictionaries.empty')}</div>
          )}
        </DataList>
      )}

      {dictionary && (
        <RowModal
          dictionary={dictionary}
          row={modal.row}
          open={modal.open}
          setOpen={(next) =>
            setModal((current) => ({
              ...current,
              open: typeof next === 'function' ? next(current.open) : next,
            }))
          }
          onSaved={() => slug !== null && void loadRows(slug)}
        />
      )}
    </>
  );
}
