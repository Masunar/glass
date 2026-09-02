import {
  Box,
  Chip,
  List,
  ListItemButton,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableRow,
  Tab,
  Tabs,
  Typography,
} from '@mui/material';

import Heading from '@app-components/Heading';
import { appRoutes } from '@router/app-router';

import { useEffect, useMemo, useState } from 'react';
import { PiFloppyDisk, PiTag } from 'react-icons/pi';

import { Submit } from '@salvon/components/button';
import { Card } from '@salvon/components/card';
import { Flex } from '@salvon/components/div';
import { Form, FormControl } from '@salvon/components/form';
import type { FormOnSubmit } from '@salvon/components/form/Form';
import { useForm } from '@salvon/hooks/useForm';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { validationCompleted } from '@salvon/utils/api-validation';
import { notifySuccess } from '@salvon/utils/notify';

import {
  type PriceCellInput,
  type PriceMatrix,
  PriceListApi,
} from '@app/api/PriceListApi';

import {
  computePrice,
  marginFromCoefficient,
  priceListSections,
} from './_components/sections';

const cellKey = (productId: number, sectionId: number) =>
  `${productId}_${sectionId}`;

export default function Page() {
  const t = useTranslation();
  const form = useForm();
  const [matrix, setMatrix] = useState<PriceMatrix | null>(null);
  const [section, setSection] = useState<string>('glass');
  const [groupId, setGroupId] = useState<number | null>(null);
  const [saving, setSaving] = useState(false);

  const load = async (nextSection: string, nextGroupId: number | null) => {
    const { content } = await PriceListApi.matrix(nextSection, nextGroupId);
    const data: PriceMatrix | undefined = content?.data;

    if (!data) {
      return;
    }

    setMatrix(data);
    setGroupId(data.group_id);
    form.reset(
      Object.fromEntries(
        data.rows.flatMap((row) =>
          data.columns.map((column) => [
            cellKey(row.product_id, column.id),
            row.cells[String(column.id)]?.coefficient ?? '',
          ]),
        ),
      ),
    );
  };

  useEffect(() => {
    void load(section, null);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [section]);

  const values = form.watch();
  const dirtyKeys = Object.keys(form.formState.dirtyFields);

  const rowsById = useMemo(
    () => new Map((matrix?.rows ?? []).map((row) => [row.product_id, row])),
    [matrix],
  );

  const handleSubmit: FormOnSubmit = async (data) => {
    if (dirtyKeys.length === 0) {
      return;
    }

    setSaving(true);

    // Wysylamy wylacznie zmienione komorki. Zapis jest niepodzielny -
    // odrzucenie jednej wartosci wstrzymuje pozostale.
    const cells: PriceCellInput[] = dirtyKeys.map((key) => {
      const [productId, sectionId] = key.split('_').map(Number);
      const raw = String(data[key] ?? '').replace(',', '.');

      return {
        product_id: productId,
        price_section_id: sectionId,
        coefficient: raw === '' ? null : raw,
        manual_net_price: null,
      };
    });

    const { content } = await PriceListApi.save(cells);

    setSaving(false);

    if (!validationCompleted(content, form.setError, t)) {
      return;
    }

    notifySuccess(t('page.price_list.saved'));
    await load(section, groupId);
  };

  return (
    <Form onSubmit={handleSubmit} form={form}>
      <Flex column gap={2}>
        <Heading
          returnTo={{ path: appRoutes.index }}
          icon={<PiTag />}
          title={t('page.price_list.title')}
        >
          <Submit
            loading={saving}
            disabled={dirtyKeys.length === 0}
            color="primary"
            variant="contained"
            icon={<PiFloppyDisk />}
          >
            {dirtyKeys.length > 0
              ? t('page.price_list.save_count', { count: dirtyKeys.length })
              : t('save')}
          </Submit>
        </Heading>

        <Typography variant="body2" sx={{ color: 'text.secondary', mt: -1 }}>
          {t('page.price_list.lead')}
        </Typography>

        <Tabs
          value={section}
          onChange={(_, next: string) => setSection(next)}
          variant="scrollable"
          scrollButtons="auto"
        >
          {priceListSections.map((item) => (
            <Tab key={item.value} value={item.value} label={t(item.labelKey)} />
          ))}
        </Tabs>

        <Flex gap={2} sx={{ alignItems: 'flex-start' }}>
          <Card sx={{ width: 240, flex: '0 0 auto' }}>
            <List dense disablePadding>
              {(matrix?.groups ?? []).map((group) => (
                <ListItemButton
                  key={group.id}
                  selected={group.id === groupId}
                  onClick={() => {
                    setGroupId(group.id);
                    void load(section, group.id);
                  }}
                  sx={{ borderRadius: 1 }}
                >
                  <Typography variant="body2">{group.name}</Typography>
                </ListItemButton>
              ))}
              {(matrix?.groups ?? []).length === 0 && (
                <Typography variant="body2" sx={{ color: 'text.secondary', p: 1 }}>
                  {t('page.price_list.no_groups')}
                </Typography>
              )}
            </List>
          </Card>

          <Card sx={{ flex: 1, overflowX: 'auto' }}>
            <Table size="small" sx={{ minWidth: 900 }}>
              <TableHead>
                <TableRow>
                  <TableCell>{t('page.price_list.column.product')}</TableCell>
                  <TableCell align="right">
                    {t('page.price_list.column.purchase')}
                  </TableCell>
                  {(matrix?.columns ?? []).map((column) => (
                    <TableCell key={column.id} align="center">
                      {column.name}
                      {column.is_default && (
                        <Chip
                          size="small"
                          variant="outlined"
                          label={t('page.price_list.default')}
                          sx={{ ml: 0.75, height: 18, fontSize: '.65rem' }}
                        />
                      )}
                    </TableCell>
                  ))}
                </TableRow>
              </TableHead>
              <TableBody>
                {(matrix?.rows ?? []).map((row) => (
                  <TableRow key={row.product_id} hover>
                    <TableCell sx={{ whiteSpace: 'nowrap' }}>
                      {row.name}
                    </TableCell>
                    <TableCell align="right" sx={{ whiteSpace: 'nowrap' }}>
                      {row.purchase_net_price ?? (
                        <Typography
                          variant="caption"
                          sx={{ color: 'warning.main' }}
                        >
                          {t('page.price_list.no_purchase_price')}
                        </Typography>
                      )}
                    </TableCell>

                    {(matrix?.columns ?? []).map((column) => {
                      const key = cellKey(row.product_id, column.id);
                      const cell = row.cells[String(column.id)];
                      const typed = String(values[key] ?? '');
                      const preview = computePrice(row.purchase_net_price, typed);
                      const margin = marginFromCoefficient(typed);
                      const isDirty = dirtyKeys.includes(key);

                      return (
                        <TableCell key={column.id} align="center">
                          <Box sx={{ width: 108, mx: 'auto' }}>
                            <FormControl
                              variant="text"
                              name={key}
                              size="small"
                              placeholder="—"
                              slotProps={{
                                htmlInput: {
                                  style: { textAlign: 'center' },
                                  inputMode: 'decimal',
                                },
                              }}
                            />
                            <Typography
                              variant="caption"
                              component="div"
                              sx={{
                                mt: 0.25,
                                fontWeight: 600,
                                color: isDirty ? 'primary.main' : 'text.primary',
                              }}
                            >
                              {preview ?? '—'}
                            </Typography>
                            <Typography
                              variant="caption"
                              component="div"
                              sx={{ color: 'text.secondary' }}
                            >
                              {margin === null
                                ? ''
                                : t('page.price_list.margin', { value: margin })}
                            </Typography>
                            {cell?.is_stale && !isDirty && (
                              <Typography
                                variant="caption"
                                component="div"
                                sx={{ color: 'warning.main' }}
                              >
                                {t('page.price_list.stale', {
                                  value: cell.recomputed_net_price ?? '',
                                })}
                              </Typography>
                            )}
                          </Box>
                        </TableCell>
                      );
                    })}
                  </TableRow>
                ))}

                {rowsById.size === 0 && (
                  <TableRow>
                    <TableCell colSpan={2 + (matrix?.columns.length ?? 0)}>
                      <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                        {t('page.price_list.no_rows')}
                      </Typography>
                    </TableCell>
                  </TableRow>
                )}
              </TableBody>
            </Table>
          </Card>
        </Flex>
      </Flex>
    </Form>
  );
}
