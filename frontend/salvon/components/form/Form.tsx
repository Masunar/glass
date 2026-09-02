import type { CSSProperties, ReactNode } from 'react';
import {
  type FormSubmitHandler,
  FormProvider as RHFProvider,
  type SubmitErrorHandler,
  type UseFormProps,
  type UseFormReturn,
  useForm,
} from 'react-hook-form';

export type FormOnSubmit = FormSubmitHandler<any>;

type BaseFormProps = {
  useFormProps?: UseFormProps<any>;
  children: ReactNode;
  onSubmit: FormOnSubmit;
  onValidationFailed?: SubmitErrorHandler<any>;
  style?: CSSProperties;
};

type UncontrolledFormProps = Omit<BaseFormProps, 'useFormProps'> & {
  form: UseFormReturn<any>;
  useFormProps?: undefined;
};

export type FormProps = BaseFormProps & {
  form?: UseFormReturn<any>;
};

export default function Form({
  children,
  onSubmit,
  onValidationFailed,
  form,
  useFormProps,
  style,
}: FormProps) {
  if (form) {
    return (
      <UncontrolledForm
        onSubmit={onSubmit}
        onValidationFailed={onValidationFailed}
        form={form}
        style={style}
      >
        {children}
      </UncontrolledForm>
    );
  }

  return (
    <ControlledForm
      useFormProps={useFormProps}
      onSubmit={onSubmit}
      onValidationFailed={onValidationFailed}
      style={style}
    >
      {children}
    </ControlledForm>
  );
}

export function UncontrolledForm({
  children,
  onSubmit,
  onValidationFailed,
  form,
  style,
}: UncontrolledFormProps) {
  const { handleSubmit } = form;

  return (
    <RHFProvider {...form}>
      <form
        noValidate
        onSubmit={handleSubmit(onSubmit, onValidationFailed)}
        style={{
          width: '100%',
          ...(style ?? {}),
        }}
      >
        {children}
      </form>
    </RHFProvider>
  );
}

export function ControlledForm({
  children,
  onSubmit,
  onValidationFailed,
  useFormProps,
  style,
}: BaseFormProps) {
  const form = useForm(useFormProps);

  return (
    <UncontrolledForm
      onSubmit={onSubmit}
      onValidationFailed={onValidationFailed}
      form={form}
      style={style}
    >
      {children}
    </UncontrolledForm>
  );
}
