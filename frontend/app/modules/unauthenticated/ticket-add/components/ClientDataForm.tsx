import * as React from "react";

import type { ClientData, ClientDataErrors } from "../types";

export interface ClientDataFormProps {
  data: ClientData;
  errors?: ClientDataErrors;
  onChange: (data: ClientData) => void;
  isDisabled?: boolean;
}

type ClientField = keyof ClientData;

const fieldLabels: Record<ClientField, { label: string; optional?: boolean }> = {
  email: { label: "E-mail", optional: true },
  phone: { label: "Telefon", optional: true },
  firstName: { label: "Imię", optional: true },
  lastName: { label: "Nazwisko", optional: true },
};

export const ClientDataForm: React.FC<ClientDataFormProps> = ({
  data,
  errors,
  onChange,
  isDisabled,
}) => {
  const generalErrorId = React.useId();

  const createChangeHandler =
    (field: ClientField) => (event: React.ChangeEvent<HTMLInputElement>) => {
      const value = event.target.value;
      onChange({
        ...data,
        [field]: value,
      });
    };

  const getFieldErrorId = (field: ClientField) => `${field}-error-${generalErrorId}`;

  return (
    <fieldset className="space-y-4" aria-describedby={errors?.general ? generalErrorId : undefined}>
      <legend className="text-base font-semibold text-slate-900 dark:text-slate-100">
        Dane kontaktowe klienta
      </legend>

      <p className="text-sm text-slate-600 dark:text-slate-300">
        Podaj przynajmniej adres e-mail lub telefon, aby zespół mógł się skontaktować.
      </p>

      {errors?.general ? (
        <div
          id={generalErrorId}
          className="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200"
          role="alert"
          aria-live="polite"
        >
          {errors.general}
        </div>
      ) : null}

      {(Object.keys(fieldLabels) as ClientField[]).map((field) => {
        const fieldConfig = fieldLabels[field];
        const errorMessage = errors?.[field];
        const fieldId = `${field}-input-${generalErrorId}`;
        const errorId = errorMessage ? getFieldErrorId(field) : undefined;

        return (
          <div key={field} className="flex flex-col gap-1">
            <label
              htmlFor={fieldId}
              className="text-sm font-medium text-slate-700 dark:text-slate-200"
            >
              {fieldConfig.label} {fieldConfig.optional ? "(opcjonalne)" : ""}
            </label>
            <input
              id={fieldId}
              name={`client.${field}`}
              value={data[field] ?? ""}
              onChange={createChangeHandler(field)}
              disabled={isDisabled}
              className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:cursor-not-allowed disabled:bg-slate-100 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
              aria-invalid={errorMessage ? "true" : undefined}
              aria-describedby={errorId}
              data-error-field={`client.${field}`}
              autoComplete={field === "email" ? "email" : field === "phone" ? "tel" : "off"}
              inputMode={field === "phone" ? "tel" : undefined}
            />
            {errorMessage ? (
              <p
                id={errorId}
                className="text-xs text-red-600 dark:text-red-300"
                role="alert"
              >
                {errorMessage}
              </p>
            ) : null}
          </div>
        );
      })}
    </fieldset>
  );
};


