import { useState } from 'react';
import { MdCreditCard } from 'react-icons/md';

import { Card } from '@salvon/components/card';
import { CheckboxGroup } from '@salvon/components/checkbox-group';
import { Flex } from '@salvon/components/div';
import { RadioGroup } from '@salvon/components/radio-group';

const options = [
  {
    value: 'standard',
    label: 'Phones',
    description: 'Lorem ipsum dolor sit amet',
  },
  {
    value: 'custom',
    label: 'Laptops',
    description: 'Consectetur adipiscing elit sed do',
  },
  {
    value: 'individual',
    label: 'Tablets',
    description: 'Eiusmod tempor incididunt ut labore',
  },
];

export default function ControlCards() {
  const [radio, setRadio] = useState('standard');
  const [checks, setChecks] = useState<string[]>(['standard']);

  return (
    <Card
      fw
      heading={{
        icon: <MdCreditCard />,
        title: 'RadioCard / CheckboxCard',
        subtitle: 'Karty z opisem — pojedynczy i wielokrotny wybór',
      }}
    >
      <Flex column gap={3}>
        <RadioGroup
          label="RadioCard (single)"
          component="radio-card"
          options={options}
          value={radio}
          onChange={(v) => setRadio(v)}
        />
        <CheckboxGroup
          label="CheckboxCard (multiple)"
          component="checkbox-card"
          options={options}
          value={checks}
          onChange={(v) => setChecks(v)}
        />
      </Flex>
    </Card>
  );
}
