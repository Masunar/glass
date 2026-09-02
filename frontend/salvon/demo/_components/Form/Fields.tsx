import { AdapterDayjs } from '@mui/x-date-pickers/AdapterDayjs';
import { LocalizationProvider } from '@mui/x-date-pickers/LocalizationProvider';

import dayjs from 'dayjs';

import { Flex } from '@salvon/components/div';
import { FormControl } from '@salvon/components/form';
import { useCurrentForm } from '@salvon/hooks/useForm';
import { useMounted } from '@salvon/hooks/useMounted';
import { useTranslation } from '@salvon/hooks/useTranslation';
import { requiredRule } from '@salvon/utils/validation-rules';

type Props = {};
export default function Fields({}: Props) {
  const t = useTranslation();
  const form = useCurrentForm();

  useMounted(() => {
    console.log(form);
  });

  return (
    <Flex column gap={1}>
      <FormControl
        variant="rating"
        name="form_rating"
        label="Form rating"
        defaultValue={4}
      />
      <FormControl variant="slider" name="form_slider" label="Form slider" />
      <FormControl
        variant="slider"
        name="form_slider_range"
        label="Form slider range"
        range
      />
      <FormControl variant="native" name="form_native_input" />
      <FormControl variant="text" name="form_text" label="Form text" />
      <FormControl
        variant="integer"
        name="form_int"
        label="Form int"
        min={100}
        max={105}
      />
      <FormControl
        variant="password"
        name="form_password"
        label="Form password"
      />
      <FormControl
        variant="switch"
        name={'form_switch'}
        label="Form switch"
        defaultValue={true}
        rules={requiredRule(t)}
      />
      <FormControl
        variant="checkbox"
        name={'form_checkbox'}
        label="Form checkbox"
        defaultValue={true}
      />
      <FormControl
        variant="control_card"
        name="form_control_card"
        label="Form control card"
        description="Lorem ipsum dolor sit amet"
        defaultValue={true}
      />
      <FormControl
        variant="control_card"
        name="form_control_card_radio"
        control="radio"
        label="Form control card radio"
        description="Consectetur adipiscing elit"
      />
      <FormControl
        variant="select"
        name="form_select_multiple"
        label="Form select multiple"
        defaultValue={[1, 2]}
        multiple
        options={[
          {
            value: 1,
            label: 'test one',
          },
          {
            value: 2,
            label: 'test two',
          },
        ]}
        required
      />
      <FormControl
        variant="select"
        name="form_select_single"
        label="Form select single"
        defaultValue={1}
        options={[
          {
            value: 1,
            label: 'test one',
          },
          {
            value: 2,
            label: 'test two',
          },
        ]}
        required
      />
      <FormControl
        variant="select"
        name="form_select_checkbox"
        label="Form select checkbox"
        defaultValue={1}
        checkboxes
        rules={requiredRule(t)}
        options={[
          {
            value: 1,
            label: 'test one',
          },
          {
            value: 2,
            label: 'test two',
          },
        ]}
        required
      />
      <FormControl
        variant="radio_group"
        name="form_radio_group"
        label="Form radio group"
        defaultValue={1}
        rules={requiredRule(t)}
        options={[
          {
            value: 1,
            label: 'test one',
          },
          {
            value: 2,
            label: 'test two',
          },
        ]}
        required
      />
      <FormControl
        component="checkbox"
        variant="radio_group"
        name="form_radio_group"
        label="Form radio group checkbox control"
        defaultValue={1}
        rules={requiredRule(t)}
        options={[
          {
            value: 1,
            label: 'test one',
          },
          {
            value: 2,
            label: 'test two',
          },
        ]}
        required
      />
      <FormControl
        variant="checkbox_group"
        name="form_checkbox_group"
        label="Form checkbox group"
        defaultValue={1}
        rules={requiredRule(t)}
        options={[
          {
            value: 1,
            label: 'test one',
          },
          {
            value: 2,
            label: 'test two',
          },
        ]}
        required
      />
      <FormControl
        component="radio"
        variant="checkbox_group"
        name="form_checkbox_group"
        label="Form checkbox group radio control"
        defaultValue={1}
        rules={requiredRule(t)}
        options={[
          {
            value: 1,
            label: 'test one',
          },
          {
            value: 2,
            label: 'test two',
          },
        ]}
        required
      />
      <LocalizationProvider dateAdapter={AdapterDayjs}>
        <FormControl
          variant="date"
          name="form_date"
          defaultValue={dayjs()}
          label="Form date"
        />
        <FormControl
          variant="date_time"
          name="form_date_time"
          defaultValue={dayjs()}
          label="Form date time"
        />
        <FormControl
          variant="time"
          name="form_time"
          defaultValue={dayjs()}
          label="Form time"
        />
      </LocalizationProvider>
      <FormControl
        variant="picker_input"
        name="form_picker_input"
        label="Form picker input"
        mode="datetime"
        displayFormat="DD.MM.YYYY HH:mm"
        defaultValue={dayjs()}
        rules={requiredRule(t)}
        required
      />
      <FormControl
        variant="picker_range"
        name="form_picker_range"
        label="Form picker range"
        defaultValue={{
          start: dayjs(),
          end: dayjs().add(7, 'day'),
        }}
      />
    </Flex>
  );
}
