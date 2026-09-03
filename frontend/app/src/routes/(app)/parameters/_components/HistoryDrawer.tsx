import { useEffect, useState } from 'react';

import { useTranslation } from '@salvon/hooks/useTranslation';

import {
  type ParameterHistoryEntry,
  ParametersApi,
} from '@app/api/ParametersApi';
import Drawer, { DrawerColumn } from '@app/components/drawer/Drawer';

/**
 * Historia zapisów zestawu parametrów.
 *
 * Wersjonowanie zapisu istniało od początku, ale nie było go gdzie
 * zobaczyć. Bez tego pytanie „dlaczego oferta sprzed miesiąca miała inną
 * cenę" nie miało odpowiedzi — a to jedyny powód, dla którego parametry
 * są w ogóle wersjonowane.
 */
export default function HistoryDrawer({
  open,
  onClose,
}: {
  open: boolean;
  onClose: () => void;
}) {
  const t = useTranslation();
  const [entries, setEntries] = useState<ParameterHistoryEntry[]>([]);
  const [loaded, setLoaded] = useState(false);

  useEffect(() => {
    if (!open) {
      return;
    }

    void (async () => {
      const { content } = await ParametersApi.history();

      setEntries(content?.data?.entries ?? []);
      setLoaded(true);
    })();
  }, [open]);

  return (
    <Drawer
      narrow
      open={open}
      onClose={onClose}
      kicker={t('page.parameters.title')}
      title={t('page.parameters.history')}
    >
      <DrawerColumn>
        {entries.map((entry) => (
          <div key={entry.version} className="ge-hist">
            <div className="ge-hist__head">
              <span className="ge-hist__version">
                {t('page.parameters.version', { count: entry.version })}
              </span>
              <span className="ge-note">
                {[entry.at, entry.by].filter(Boolean).join(' · ')}
              </span>
            </div>

            {entry.changes.map((change) => (
              <div key={change.field} className="ge-hist__change">
                <span>{t(`page.parameters.field.${change.field}`)}</span>
                <span className="ge-note">
                  {change.before ?? '—'} →{' '}
                  <strong>{change.after ?? '—'}</strong>
                </span>
              </div>
            ))}
          </div>
        ))}

        {loaded && entries.length === 0 && (
          <p className="ge-note">{t('page.parameters.history_empty')}</p>
        )}
      </DrawerColumn>
    </Drawer>
  );
}
