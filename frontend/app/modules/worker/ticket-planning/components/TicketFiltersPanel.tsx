import * as React from "react";

import type { TicketCategory, TicketPriority, TicketStatus } from "~/api/types";

import type { TicketPlanningFilters, TicketPlanningSortOption } from "../TicketPlanning";

export interface TicketFiltersPanelProps {
  filters: TicketPlanningFilters;
  sortBy: string;
  availableCategories: TicketCategory[];
  isLoading?: boolean;
  error?: string | null;
  onFiltersChange: (filters: TicketPlanningFilters) => void;
  onSortChange: (sortBy: TicketPlanningSortOption) => void;
  onRetry?: () => void;
}

const ticketStatuses: TicketStatus[] = [
  "waiting",
  "in_progress",
  "completed",
  "awaiting_response",
  "awaiting_client",
  "closed",
];

const ticketPriorities: TicketPriority[] = ["low", "normal", "high", "urgent"];

const sortOptions: Array<{ value: TicketPlanningSortOption; label: string }> = [
  { value: "created_at", label: "Data utworzenia" },
  { value: "priority", label: "Priorytet" },
  { value: "category", label: "Kategoria" },
  { value: "estimated_time", label: "Szacowany czas" },
];

const normalizeSortValue = (sortBy: TicketPlanningSortOption) => sortBy;

export const TicketFiltersPanel: React.FC<TicketFiltersPanelProps> = ({
  filters,
  sortBy,
  availableCategories,
  isLoading,
  error,
  onFiltersChange,
  onSortChange,
  onRetry,
}) => {
  const handleCategoryToggle = (categoryId: string) => {
    const nextCategories = filters.categories.includes(categoryId)
      ? filters.categories.filter((id) => id !== categoryId)
      : [...filters.categories, categoryId];
    onFiltersChange({
      ...filters,
      categories: nextCategories,
    });
  };

  const handleStatusToggle = (status: TicketStatus) => {
    const nextStatuses = filters.statuses.includes(status)
      ? filters.statuses.filter((value) => value !== status)
      : [...filters.statuses, status];
    onFiltersChange({
      ...filters,
      statuses: nextStatuses,
    });
  };

  const handlePriorityToggle = (priority: TicketPriority) => {
    const nextPriorities = filters.priorities.includes(priority)
      ? filters.priorities.filter((value) => value !== priority)
      : [...filters.priorities, priority];
    onFiltersChange({
      ...filters,
      priorities: nextPriorities,
    });
  };

  const handleReset = () => {
    onFiltersChange({
      categories: [],
      statuses: [],
      priorities: [],
      searchQuery: "",
    });
    onSortChange("priority");
  };

  return (
    <section className="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
      <header className="flex items-center justify-between border-b border-slate-200 px-4 py-3 dark:border-slate-800">
        <div>
          <h2 className="text-lg font-semibold text-slate-900 dark:text-slate-100">
            Filtrowanie ticketów
          </h2>
          <p className="text-xs text-slate-500 dark:text-slate-400">
            Zawęź widok backlogu lub zmień kolejność sortowania.
          </p>
        </div>
        <button
          type="button"
          onClick={handleReset}
          className="rounded-md border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-600 transition hover:bg-slate-100 focus:outline-none focus-visible:ring focus-visible:ring-slate-300 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
        >
          Resetuj filtry
        </button>
      </header>

      <div className="grid gap-4 px-4 py-4 md:grid-cols-2 lg:grid-cols-4">
        <div className="flex flex-col gap-2">
          <label htmlFor="ticket-search" className="text-xs font-semibold text-slate-600 dark:text-slate-300">
            Wyszukiwanie
          </label>
          <input
            id="ticket-search"
            type="search"
            className="rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:outline-none focus:ring focus:ring-emerald-500/30 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
            placeholder="Szukaj po tytule lub kliencie..."
            value={filters.searchQuery}
            onChange={(event) =>
              onFiltersChange({
                ...filters,
                searchQuery: event.target.value,
              })
            }
          />
        </div>

        <div className="flex flex-col gap-2">
          <label className="text-xs font-semibold text-slate-600 dark:text-slate-300">
            Kategorie
          </label>
          {isLoading ? (
            <p className="text-xs text-slate-500">Ładowanie kategorii...</p>
          ) : null}
          {error ? (
            <div className="text-xs text-red-500">
              Nie udało się pobrać kategorii.
              {onRetry ? (
                <button
                  type="button"
                  className="ml-2 text-xs font-semibold text-red-600 underline hover:text-red-500"
                  onClick={onRetry}
                >
                  Spróbuj ponownie
                </button>
              ) : null}
            </div>
          ) : null}

          <div className="flex flex-wrap gap-2">
            {availableCategories.map((category) => (
              <label
                key={category.id}
                className={`inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs transition ${
                  filters.categories.includes(category.id)
                    ? "border-emerald-500 bg-emerald-50 text-emerald-700 dark:border-emerald-400 dark:bg-emerald-900/40 dark:text-emerald-300"
                    : "border-slate-300 bg-white text-slate-600 hover:border-emerald-400 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                }`}
              >
                <input
                  type="checkbox"
                  className="h-3 w-3 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-900"
                  checked={filters.categories.includes(category.id)}
                  onChange={() => handleCategoryToggle(category.id)}
                />
                {category.name}
              </label>
            ))}
            {availableCategories.length === 0 && !isLoading && !error ? (
              <p className="text-xs text-slate-500 dark:text-slate-400">Brak kategorii.</p>
            ) : null}
          </div>
        </div>

        <div className="flex flex-col gap-2">
          <label className="text-xs font-semibold text-slate-600 dark:text-slate-300">
            Statusy
          </label>
          <div className="flex flex-wrap gap-2">
            {ticketStatuses.map((status) => (
              <button
                key={status}
                type="button"
                className={`rounded-full px-3 py-1 text-xs font-medium transition ${
                  filters.statuses.includes(status)
                    ? "bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900"
                    : "bg-slate-200 text-slate-700 hover:bg-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                }`}
                onClick={() => handleStatusToggle(status)}
              >
                {status}
              </button>
            ))}
          </div>
        </div>

        <div className="flex flex-col gap-2">
          <label className="text-xs font-semibold text-slate-600 dark:text-slate-300">
            Priorytety
          </label>
          <div className="flex flex-wrap gap-2">
            {ticketPriorities.map((priority) => (
              <button
                key={priority}
                type="button"
                className={`rounded-full px-3 py-1 text-xs font-medium transition ${
                  filters.priorities.includes(priority)
                    ? "bg-emerald-600 text-white hover:bg-emerald-700"
                    : "bg-emerald-100 text-emerald-700 hover:bg-emerald-200 dark:bg-emerald-900/40 dark:text-emerald-300 dark:hover:bg-emerald-900/60"
                }`}
                onClick={() => handlePriorityToggle(priority)}
              >
                {priority}
              </button>
            ))}
          </div>
        </div>

        <div className="flex flex-col gap-2">
          <label htmlFor="ticket-sort" className="text-xs font-semibold text-slate-600 dark:text-slate-300">
            Sortowanie
          </label>
          <select
            id="ticket-sort"
            className="rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:outline-none focus:ring focus:ring-emerald-500/30 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
            value={normalizeSortValue(sortBy)}
            onChange={(event) => onSortChange(event.target.value as TicketPlanningSortOption)}
          >
            {sortOptions.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
        </div>
      </div>
    </section>
  );
};


