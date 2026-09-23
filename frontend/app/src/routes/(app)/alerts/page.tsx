import RuleDrawer from './_components/RuleDrawer';
import { useEffect, useState } from 'react';
import { PiPlus, PiWarningCircle } from 'react-icons/pi';

import { Button } from '@salvon/components/button';
import { useTranslation } from '@salvon/hooks/useTranslation';

import type { AlertBoard, AlertRuleRow } from '@app/api/AlertsApi';
import { AlertsApi } from '@app/api/AlertsApi';
import HasPermission from '@app/components/HasPermission';
import { ListWait } from '@app/components/list';
import { Permission, SubPermission } from '@app/config/permission';

/**
 * Reguły alertów.
 *
 * Ekran odpowiada na jedno pytanie: **czy to, co świeci się w systemie,
 * jest tym, czego pilnujemy**. Stąd kolumna „otwarte" przy każdej
 * regule — reguła włączona i nigdy niezapalona to albo próg ustawiony
 * za wysoko, albo warunek, którego dane nie potrafią spełnić.
 *
 * Reguła wyłączona nie pokazuje zera, tylko kreskę: nic jej nie
 * przelicza, więc zero byłoby zmyśloną liczbą.
 */
export default function Page() {
  const t = useTranslation();
  const [board, setBoard] = useState<AlertBoard | null>(null);
  const [loading, setLoading] = useState(true);
  const [drawer, setDrawer] = useState<{
    open: boolean;
    rule: AlertRuleRow | null;
  }>({ open: false, rule: null });

  const load = async () => {
    setLoading(true);

    const { content } = await AlertsApi.board();
    const data: AlertBoard | undefined = content?.data;

    setLoading(false);

    if (data) {
      setBoard(data);
    }
  };

  useEffect(() => {
    void load();
  }, []);

  if (!board) {
    return (
      <>
        <ListWait on={loading} />
        {!loading && <div className="ge-empty">{t('page.alerts.failed')}</div>}
      </>
    );
  }

  const active = board.rules.filter((rule) => rule.is_active).length;

  return (
    <>
      <header className="ge-head">
        <div>
          <div className="ge-head__kicker">{t('page.module.adm')}</div>
          <h1 className="ge-head__title">
            {t('page.alerts.count', { count: active })}
          </h1>
          <div className="ge-quiet">{t('page.alerts.lead')}</div>
        </div>

        <div className="ge-head__actions">
          <HasPermission
            permission={Permission.ALERTS}
            sub={SubPermission.CREATE}
          >
            <Button
              variant="contained"
              icon={<PiPlus />}
              onClick={() => setDrawer({ open: true, rule: null })}
            >
              {t('page.alerts.new')}
            </Button>
          </HasPermission>
        </div>
      </header>

      <section className="ge-section">
        <div className="ge-alerts__head">
          <span>{t('page.alerts.rule')}</span>
          <span>{t('page.alerts.type')}</span>
          <span>{t('page.alerts.label')}</span>
          <span className="r">{t('page.alerts.open')}</span>
          <span>{t('page.alerts.state')}</span>
        </div>

        <ListWait on={loading} />

        {board.rules.map((rule) => (
          <div className="ge-alerts__row" key={rule.id}>
            <span>
              <button
                type="button"
                className="ge-link ge-acc__as-link"
                onClick={() => setDrawer({ open: true, rule })}
              >
                {rule.name}
              </button>
              <div className="ge-note">{rule.code}</div>
            </span>

            <span>
              {rule.type_label ?? (
                /* Typ, ktorego katalog nie zna, to regula zepsuta, a nie
                   wylaczona — i ma tak wygladac. */
                <span className="ge-note ge-note--warn">
                  <PiWarningCircle /> {rule.type}
                </span>
              )}
            </span>

            <span>
              <span
                className="ge-mark"
                style={
                  rule.color
                    ? ({ '--ge-mark': rule.color } as React.CSSProperties)
                    : undefined
                }
              >
                {rule.label}
              </span>
            </span>

            <span className="r">{rule.open ?? '—'}</span>

            <span>
              {rule.is_active ? (
                <span className="ge-quiet">{t('page.alerts.on')}</span>
              ) : (
                <span className="ge-tag">{t('page.alerts.off')}</span>
              )}
            </span>
          </div>
        ))}

        {board.rules.length === 0 && (
          <div className="ge-list__empty">{t('page.alerts.empty')}</div>
        )}
      </section>

      <RuleDrawer
        board={board}
        rule={drawer.rule}
        open={drawer.open}
        onClose={() => setDrawer({ open: false, rule: null })}
        onSaved={() => {
          setDrawer({ open: false, rule: null });
          void load();
        }}
      />
    </>
  );
}
