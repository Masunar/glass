import { Typography } from '@mui/material';

import ContractorModal from './_components/ContractorModal';
import PriceSectionsModal from './_components/PriceSectionsModal';
import { useEffect, useState } from 'react';
import { PiPencilSimple, PiPlus, PiTag } from 'react-icons/pi';

import { Button } from '@salvon/components/button';
import { Flex } from '@salvon/components/div';
import { useTranslation } from '@salvon/hooks/useTranslation';

import {
  type ContractorCard,
  type ContractorRow,
  ContractorsApi,
} from '@app/api/ContractorsApi';
import HasPermission from '@app/components/HasPermission';
import {
  type Column,
  DataList,
  ListHead,
  Row,
  Stage,
} from '@app/components/list';
import { Permission, SubPermission } from '@app/config/permission';

const columns: Column[] = [
  { labelKey: 'page.contractors.column.name', width: '1fr' },
  { labelKey: 'page.contractors.column.contact', width: '210px' },
  { labelKey: 'page.contractors.column.terms', width: '170px' },
  { labelKey: 'page.contractors.column.status', width: '130px' },
  { labelKey: 'page.contractors.column.actions', width: '150px' },
];

export default function Page() {
  const t = useTranslation();
  const [rows, setRows] = useState<ContractorRow[]>([]);
  const [total, setTotal] = useState(0);
  const [query, setQuery] = useState('');
  const [includeInactive, setIncludeInactive] = useState(false);
  const [card, setCard] = useState<ContractorCard | null>(null);
  const [formOpen, setFormOpen] = useState(false);
  const [sectionsOpen, setSectionsOpen] = useState(false);

  const load = async (
    search: string = query,
    inactive: boolean = includeInactive,
  ) => {
    const { content } = await ContractorsApi.search(search, inactive);

    setRows(content?.data?.contractors ?? []);
    setTotal(Number(content?.data?.total ?? 0));
  };

  useEffect(() => {
    void load('', false);
  }, []);

  const openCard = async (id: number, target: 'form' | 'sections') => {
    const { content } = await ContractorsApi.card(id);
    const loaded: ContractorCard | undefined = content?.data;

    if (!loaded) {
      return;
    }

    setCard(loaded);
    if (target === 'form') {
      setFormOpen(true);
    } else {
      setSectionsOpen(true);
    }
  };

  return (
    <>
      <header className="ge-head">
        <div>
          <div className="ge-head__kicker">{t('page.module.zlec')}</div>
          <h1 className="ge-head__title">{t('page.contractors.title')}</h1>
        </div>

        <div className="ge-head__actions">
          <input
            className="ge-search"
            type="search"
            value={query}
            placeholder={t('page.contractors.search')}
            aria-label={t('page.contractors.search')}
            onChange={(event) => {
              setQuery(event.target.value);
              void load(event.target.value);
            }}
          />
          <HasPermission
            permission={Permission.CONTRACTORS}
            sub={SubPermission.CREATE}
          >
            <Button
              variant="contained"
              icon={<PiPlus />}
              onClick={() => {
                setCard(null);
                setFormOpen(true);
              }}
            >
              {t('page.contractors.add')}
            </Button>
          </HasPermission>
        </div>
      </header>

      <nav className="ge-filters" aria-label={t('page.contractors.filters')}>
        <button
          type="button"
          className={includeInactive ? '' : 'is-active'}
          onClick={() => {
            setIncludeInactive(false);
            void load(query, false);
          }}
        >
          {t('page.contractors.filter.active', { count: total })}
        </button>
        <button
          type="button"
          className={includeInactive ? 'is-active' : ''}
          onClick={() => {
            setIncludeInactive(true);
            void load(query, true);
          }}
        >
          {t('page.contractors.filter.all')}
        </button>
        <span className="ge-filters__end">
          {t('page.contractors.shown', { count: rows.length })}
        </span>
      </nav>

      <DataList columns={columns}>
        <ListHead columns={columns} translate={t} />

        {rows.map((row) => (
          <Row key={row.id}>
            <div>
              <div className="ge-name">{row.display_name}</div>
              <div className="ge-note">
                {row.tax_id
                  ? `NIP ${row.tax_id}`
                  : t(`page.contractors.type.${row.type}`)}
                {row.short_name && row.short_name !== row.name
                  ? ` · ${row.name}`
                  : ''}
              </div>
            </div>

            <div>
              <div>{row.phone ?? '—'}</div>
              <div className="ge-note">{row.email ?? ''}</div>
            </div>

            <div>
              <div>
                {row.payment_days > 0
                  ? t('page.contractors.days', { count: row.payment_days })
                  : t('page.contractors.prepaid')}
              </div>
              <div className="ge-note">
                {Number(row.credit_limit) > 0
                  ? t('page.contractors.limit', { value: row.credit_limit })
                  : t('page.contractors.no_limit')}
              </div>
            </div>

            <Stage
              label={t(
                row.is_active
                  ? 'page.contractors.state.active'
                  : 'page.contractors.state.inactive',
              )}
              tone={row.is_active ? 'done' : 'idle'}
            />

            <Flex gap={1}>
              <HasPermission
                permission={Permission.CONTRACTORS}
                sub={SubPermission.UPDATE}
              >
                <Button
                  variant="text"
                  size="small"
                  icon={<PiPencilSimple />}
                  onClick={() => void openCard(row.id, 'form')}
                >
                  {t('edit')}
                </Button>
              </HasPermission>
              <HasPermission
                permission={Permission.CONTRACTORS}
                sub={SubPermission.UPDATE}
              >
                <Button
                  variant="text"
                  size="small"
                  icon={<PiTag />}
                  onClick={() => void openCard(row.id, 'sections')}
                >
                  {t('page.contractors.price_sections.short')}
                </Button>
              </HasPermission>
            </Flex>
          </Row>
        ))}

        {rows.length === 0 && (
          <div style={{ padding: '16px 24px' }}>
            <Typography variant="body2" sx={{ color: 'text.secondary' }}>
              {t('page.contractors.empty')}
            </Typography>
          </div>
        )}
      </DataList>

      <ContractorModal
        card={card}
        open={formOpen}
        setOpen={setFormOpen}
        onSaved={() => void load()}
      />

      <PriceSectionsModal
        card={card}
        open={sectionsOpen}
        setOpen={setSectionsOpen}
        onSaved={() => void load()}
      />
    </>
  );
}
