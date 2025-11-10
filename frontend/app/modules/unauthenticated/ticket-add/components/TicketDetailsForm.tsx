import * as React from "react";

export interface TicketDetailsFormValues {
  title?: string;
  description?: string;
}

export interface TicketDetailsFormErrors {
  title?: string;
  description?: string;
}

export interface TicketDetailsFormProps {
  values: TicketDetailsFormValues;
  errors?: TicketDetailsFormErrors;
  onChange: (values: TicketDetailsFormValues) => void;
  isDisabled?: boolean;
  maxDescriptionLength?: number;
}

export const TicketDetailsForm: React.FC<TicketDetailsFormProps> = ({
  values,
  errors,
  onChange,
  isDisabled,
  maxDescriptionLength = 5000,
}) => {
  const titleId = React.useId();
  const descriptionId = React.useId();
  const descriptionCounterId = `${descriptionId}-counter`;
  const descriptionErrorId = errors?.description ? `${descriptionId}-error` : undefined;

  const handleTitleChange = (event: React.ChangeEvent<HTMLInputElement>) => {
    onChange({
      ...values,
      title: event.target.value,
    });
  };

  const handleDescriptionChange = (
    event: React.ChangeEvent<HTMLTextAreaElement>,
  ) => {
    onChange({
      ...values,
      description: event.target.value,
    });
  };

  const descriptionLength = values.description?.length ?? 0;
  const remainingCharacters = maxDescriptionLength - descriptionLength;

  return (
    <fieldset className="space-y-4">
      <legend className="text-base font-semibold text-slate-900 dark:text-slate-100">
        Szczegoly ticketa
      </legend>

      <div className="flex flex-col gap-1">
        <label
          htmlFor={titleId}
          className="text-sm font-medium text-slate-700 dark:text-slate-200"
        >
          Tytul (opcjonalny)
        </label>
        <input
          id={titleId}
          name="title"
          value={values.title ?? ""}
          onChange={handleTitleChange}
          disabled={isDisabled}
          className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:cursor-not-allowed disabled:bg-slate-100 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
          maxLength={255}
          aria-describedby={errors?.title ? `${titleId}-error` : undefined}
          aria-invalid={errors?.title ? "true" : undefined}
          data-error-field="title"
        />
        {errors?.title ? (
          <p
            id={`${titleId}-error`}
            className="text-xs text-red-600 dark:text-red-300"
            role="alert"
          >
            {errors.title}
          </p>
        ) : (
          <p className="text-xs text-slate-500 dark:text-slate-400">
            Maksymalnie 255 znakow.
          </p>
        )}
      </div>

      <div className="flex flex-col gap-1">
        <label
          htmlFor={descriptionId}
          className="text-sm font-medium text-slate-700 dark:text-slate-200"
        >
          Opis problemu (opcjonalny)
        </label>
        <textarea
          id={descriptionId}
          name="description"
          value={values.description ?? ""}
          onChange={handleDescriptionChange}
          disabled={isDisabled}
          className="h-32 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:cursor-not-allowed disabled:bg-slate-100 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
          aria-describedby={
            [descriptionCounterId, descriptionErrorId].filter(Boolean).join(" ") ||
            undefined
          }
          aria-invalid={errors?.description ? "true" : undefined}
          data-error-field="description"
        />
        <div className="flex items-baseline justify-between text-xs text-slate-500 dark:text-slate-400">
          <span id={descriptionCounterId}>
            Pozostalo znakow: {Math.max(0, remainingCharacters)}
          </span>
          <span>{descriptionLength}/{maxDescriptionLength}</span>
        </div>
        {errors?.description ? (
          <p
            id={descriptionErrorId}
            className="text-xs text-red-600 dark:text-red-300"
            role="alert"
          >
            {errors.description}
          </p>
        ) : null}
      </div>
    </fieldset>
  );
};


