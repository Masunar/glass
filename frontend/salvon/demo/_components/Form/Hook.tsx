import Fields from './Fields';

import Submit from '@salvon/components/button/Submit';
import { Flex } from '@salvon/components/div';
import { Form } from '@salvon/components/form';
import { useForm } from '@salvon/hooks/useForm';

export default function Hook() {
  const form = useForm({
    defaultValues: { form_native_input: 'test' },
    disabled: true,
  });

  return (
    <Form
      form={form}
      onSubmit={(data) => console.log(data)}
      onValidationFailed={(fields) => console.log(fields)}
    >
      <Flex column gap={2}>
        <Fields />
        <div>
          <Submit preset="save" />
        </div>
      </Flex>
    </Form>
  );
}
