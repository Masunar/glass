import { Chip } from '@mui/material';

import { PriceSettlement as PriceSettlementComponent } from '@salvon/components/price-settlement';

const vatChip = (rate: string) => (
  <Chip
    size="small"
    label={rate}
    color="success"
    variant="outlined"
    sx={{ height: 20, '& .MuiChip-label': { px: 1, fontSize: '0.72rem' } }}
  />
);

export default function PriceSettlement() {
  return (
    <PriceSettlementComponent
      title="Lorem ipsum dolor sit"
      value="1080,00 PLN"
      subtitle="Consectetur adipiscing elit sed do"
      limit={{
        label: 'Ut enim ad minim veniam',
        value: '5000 PLN',
        progress: 100,
        okMessage: 'Duis aute irure dolor',
        exceeded: true,
      }}
      breakdownTitle="Quis nostrud exercitation"
      rows={[
        { label: 'Ullamco', value: '1000,00 PLN' },
        { label: 'Laboris', badge: vatChip('8%'), value: '80,00 PLN' },
        {
          label: 'Commodo',
          value: '1080,00 PLN',
          highlighted: true,
          slotProps: { value: { sx: { color: 'success.main' } } },
        },
      ]}
    />
  );
}
