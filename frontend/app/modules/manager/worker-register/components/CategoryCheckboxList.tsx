import * as React from "react";

import type { TicketCategory } from "~/api/types";

export interface CategoryCheckboxListProps {
  categories: TicketCategory[];
  selectedCategoryIds: string[];
  onChange: (categoryIds: string[]) => void;
  error?: string;
  isDisabled?: boolean;
  isLoading?: boolean;
  isError?: boolean;
  onRetry?: () => void;
}

const formatResolutionTime = (minutes: number): string => {
  if (!Number.isFinite(minutes) || minutes <= 0) {
    return "Brak danych";
  }

  if (minutes < 60) {
    return `${minutes} min`;
  }

  const hours = Math.floor(minutes / 60);
  const remaining = minutes % 60;

  if (remaining === 0) {
    return `${hours} h`;
  }

  return `${hours} h ${remaining} min`;
};

export const CategoryCheckboxList: React.FC<CategoryCheckboxListProps> = ({
  categories,
  selectedCategoryIds,
  onChange,
  error,
  isDisabled,
  isLoading,
  isError,
  onRetry,
}) => {
  const [search, setSearch] = React.useState("");

  const sortedCategories = React.useMemo(() => {
    return [...categories].sort((a, b) => a.name.localeCompare(b.name, "pl"));
  }, [categories]);

  const filteredCategories = React.useMemo(() => {
    if (!search.trim()) {
      return sortedCategories;
    }
    const query = search.trim().toLowerCase();
    return sortedCategories.filter((category) => {
      const haystack = `${category.name} ${category.description ?? ""}`.toLowerCase();
      return haystack.includes(query);
    });
  }, [search, sortedCategories]);

  const handleToggle = (categoryId: string) => {
    onChange(
      selectedCategoryIds.includes(categoryId)
        ? selectedCategoryIds.filter((id) => id !== categoryId)
        : [...selectedCategoryIds, categoryId],
    );
  };

  const handleSelectAll = () => {
    if (filteredCategories.length === 0) {
      return;
    }

    const filteredIds = filteredCategories.map((category) => category.id);
    const allSelected = filteredIds.every((id) => selectedCategoryIds.includes(id));

    if (allSelected) {
      onChange(selectedCategoryIds.filter((id) => !filteredIds.includes(id)));
      return;
    }

    const merged = new Set([...selectedCategoryIds, ...filteredIds]);
    onChange([...merged]);
  };

  const hasCategories = categories.length > 0;
  const filteredSelectedCount = filteredCategories.filter((category) =>
    selectedCategoryIds.includes(category.id),
  ).length;

  return (
    <fieldset className="space-y-4 rounded-xl border border-slate-200 p-4 dark:border-slate-700">
      <legend className="px-2 text-sm font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">
        Kategorie ticketów
      </legend>

      <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <p className="text-sm text-slate-600 dark:text-slate-300">
          Wybierz, do jakich kolejek ma dostęp nowy pracownik.
        </p>
        <div className="flex items-center gap-2">
          <input
            type="search"
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            placeholder="Szukaj kategorii..."
            className="w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200 disabled:cursor-not-allowed disabled:bg-slate-100 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-50 dark:focus:border-blue-400 dark:focus:ring-blue-900 md:w-56"
            aria-label="Filtruj kategorie ticketów"
            disabled={isDisabled || isLoading || !hasCategories}
          />
          <button
            type="button"
            onClick={handleSelectAll}
            disabled={isDisabled || !hasCategories || isLoading}
            className="rounded-md border border-slate-300 bg-white px-3 py-1 text-xs font-medium text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
          >
            {filteredSelectedCount === filteredCategories.length && filteredSelectedCount > 0
              ? "Odznacz widoczne"
              : "Zaznacz widoczne"}
          </button>
        </div>
      </div>

      {isLoading ? (
        <div className="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
          <span className="inline-flex h-4 w-4 animate-spin rounded-full border-2 border-blue-500 border-t-transparent" />
          Ładujemy kategorie...
        </div>
      ) : null}

      {isError ? (
        <div
          className="flex flex-col gap-2 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-500/40 dark:bg-red-950/40 dark:text-red-300"
          role="alert"
        >
          <span>Nie udało się pobrać listy kategorii.</span>
          <div>
            <button
              type="button"
              onClick={() => onRetry?.()}
              className="rounded-md border border-red-200 bg-white px-2 py-1 text-xs font-medium text-red-600 transition hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-300 dark:border-red-500/40 dark:bg-red-900 dark:text-red-200"
            >
              Spróbuj ponownie
            </button>
          </div>
        </div>
      ) : null}

      {!isLoading && !isError && filteredCategories.length === 0 ? (
        <p className="text-sm text-slate-500 dark:text-slate-400">
          {hasCategories
            ? "Brak kategorii spełniających kryteria wyszukiwania."
            : "Brak dostępnych kategorii do przypisania."}
        </p>
      ) : null}

      <div className="grid gap-3">
        {filteredCategories.map((category) => {
          const checkboxId = `worker-register-category-${category.id}`;
          const isChecked = selectedCategoryIds.includes(category.id);

          return (
            <label
              key={category.id}
              htmlFor={checkboxId}
              className="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm shadow-sm transition hover:border-blue-200 hover:shadow-md has-[:disabled]:cursor-not-allowed has-[:disabled]:opacity-50 dark:border-slate-700 dark:bg-slate-900 dark:hover:border-blue-500/40"
            >
              <input
                id={checkboxId}
                type="checkbox"
                checked={isChecked}
                disabled={isDisabled}
                onChange={() => handleToggle(category.id)}
                className="mt-1 h-4 w-4 rounded border-slate-300 text-blue-600 transition focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-900 dark:text-blue-400"
              />
              <div className="space-y-1">
                <div className="flex items-center gap-2">
                  <span className="font-medium text-slate-800 dark:text-slate-100">
                    {category.name}
                  </span>
                  <span className="rounded-full bg-blue-50 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-blue-600 dark:bg-blue-500/10 dark:text-blue-300">
                    {formatResolutionTime(category.defaultResolutionTimeMinutes)}
                  </span>
                </div>
                {category.description ? (
                  <p className="text-xs text-slate-500 dark:text-slate-400">
                    {category.description}
                  </p>
                ) : null}
              </div>
            </label>
          );
        })}
      </div>

      {error ? (
        <p className="text-sm text-red-600 dark:text-red-400" role="alert">
          {error}
        </p>
      ) : null}
    </fieldset>
  );
};

CategoryCheckboxList.displayName = "CategoryCheckboxList";


