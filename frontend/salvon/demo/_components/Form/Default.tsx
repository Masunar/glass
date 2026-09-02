import Fields from './Fields';

import Submit from '@salvon/components/button/Submit';
import { Flex } from '@salvon/components/div';
import { Form } from '@salvon/components/form';

export default function Default() {
  return (
    <Form
      useFormProps={{
        defaultValues: { form_native_input: 'test' },
      }}
      onSubmit={(data) => console.log(data)}
      onValidationFailed={(fields) => console.log(fields)}
    >
      <Flex column gap={2}>
        <Fields />
        <Flex>
          <Submit preset="save" />
        </Flex>
      </Flex>
    </Form>
  );
}
