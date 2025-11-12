import * as React from "react";

import type { TicketCategory } from "~/api/types";

import { LoadingSpinner } from "./LoadingSpinner";

export interface CategorySelectorProps {
  categories?: TicketCategory[];
  selectedCategoryId?: string;
  onChange: (categoryId: string) => void;
  error?: string;
  isDisabled?: boolean;
  isLoading?: boolean;
  isError?: boolean;
  fetchErrorMessage?: string;
  onRetry?: () => void;
  isOptional?: boolean;
  optionalLabel?: string;
  optionalDescription?: string;
}

export const CategorySelector: React.FC<CategorySelectorProps> = ({
  categories = [],
  selectedCategoryId,
  onChange,
  error,
  isDisabled,
  isLoading,
  isError,
  fetchErrorMessage,
  onRetry,
  isOptional = false,
  optionalLabel,
  optionalDescription,
}) => {
  const selectId = React.useId();
  const errorId = error ? `${selectId}-error` : undefined;
  const hintId = `${selectId}-hint`;

  const handleChange = (event: React.ChangeEvent<HTMLSelectElement>) => {
    onChange(event.target.value);
  };

  const hasCategories = categories.length > 0;
  const labelSuffix = isOptional ? " (opcjonalne)" : " (wymagane)";
  const description = isOptional
    ? optionalDescription ??
      "Możesz zawęzić listę, wybierając kategorię, lub pozostaw to pole puste, aby wyszukać we wszystkich kategoriach."
    : "Wybierz kategorię z listy, aby skierować ticketa do odpowiedniego zespołu.";
  const placeholderLabel = isOptional
    ? optionalLabel ?? (hasCategories ? "Dowolna kategoria" : "Brak dostępnych kategorii")
    : hasCategories
      ? "Wybierz kategorię"
      : "Brak dostępnych kategorii";

  return (
    <fieldset className="space-y-3">
      <legend className="text-base font-semibold text-slate-900 dark:text-slate-100">
        Wybierz kategorię
      </legend>

      <p id={hintId} className="text-sm text-slate-600 dark:text-slate-300">
        {description}
      </p>

      {isLoading ? (
        <LoadingSpinner message="Ładujemy listę kategorii..." />
      ) : null}

      {isError ? (
        <div
          className="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200"
          role="alert"
        >
          <p>{fetchErrorMessage ?? "Nie udało się pobrać kategorii."}</p>
          {onRetry ? (
            <button
              type="button"
              onClick={onRetry}
              className="mt-2 inline-flex items-center gap-2 rounded-md bg-red-600 px-3 py-1 text-xs font-semibold text-white transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500"
            >
              Spróbuj ponownie
            </button>
          ) : null}
        </div>
      ) : null}

      <div className="flex flex-col gap-1">
        <label
          htmlFor={selectId}
          className="text-sm font-medium text-slate-700 dark:text-slate-200"
        >
          Kategoria{labelSuffix}
        </label>
        <select
          id={selectId}
          name="categoryId"
          value={selectedCategoryId ?? ""}
          onChange={handleChange}
          disabled={isDisabled || isLoading || (!hasCategories && !isOptional)}
          className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:cursor-not-allowed disabled:bg-slate-100 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
          aria-required={isOptional ? undefined : true}
          aria-invalid={error ? "true" : undefined}
          aria-describedby={[hintId, errorId].filter(Boolean).join(" ") || undefined}
          data-error-field="category"
        >
          <option value="" disabled={!isOptional}>
            {placeholderLabel}
          </option>
          {categories.map((category) => (
            <option key={category.id} value={category.id}>
              {category.name}
            </option>
          ))}
        </select>
        {error ? (
          <p
            id={errorId}
            className="text-xs text-red-600 dark:text-red-300"
            role="alert"
          >
            {error}
          </p>
        ) : null}
        {selectedCategoryId
          ? categories
              .filter((category) => category.id === selectedCategoryId)
              .map((category) => (
                <p
                  key={category.id}
                  className="text-xs text-slate-600 dark:text-slate-300"
                >
                  {category.description ?? "Brak dodatkowego opisu dla tej kategorii."}
                </p>
              ))
          : null}
      </div>
    </fieldset>
  );
};


