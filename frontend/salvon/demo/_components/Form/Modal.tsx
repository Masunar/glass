import Fields from './Fields';

import { Button } from '@salvon/components/button';
import { FormModal } from '@salvon/components/modal';

type Props = {};
export default function Modal({}: Props) {
  return (
    <FormModal
      useFormProps={{
        defaultValues: { form_native_input: 'test' },
      }}
      onSubmit={(data) => console.log(data)}
      onValidationFailed={(fields) => console.log(fields)}
      anchor={<Button>Open Form</Button>}
    >
      <Fields />
    </FormModal>
  );
}
