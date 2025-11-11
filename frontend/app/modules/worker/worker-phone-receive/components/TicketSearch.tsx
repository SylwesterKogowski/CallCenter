import * as React from "react";

import type { TicketStatus } from "~/api/types";
import type { WorkerTicketSearchResult } from "~/api/worker/phone";

interface TicketSearchProps {
  query: string;
  categoryId: string;
  status: TicketStatus | "";
  onQueryChange: (value: string) => void;
  onCategoryChange: (value: string) => void;
  onStatusChange: (value: TicketStatus | "") => void;
  results: WorkerTicketSearchResult[];
  isLoading: boolean;
  errorMessage: string | null;
  onRetry?: () => void;
  onTicketSelect: (ticket: WorkerTicketSearchResult) => void;
  excludeTicketId?: string;
}

const statusOptions: Array<{ value: TicketStatus; label: string }> = [
  { value: "waiting", label: "Oczekujący" },
  { value: "in_progress", label: "W toku" },
  { value: "completed", label: "Zakończony" },
  { value: "awaiting_response", label: "Oczekuje na odpowiedź" },
  { value: "awaiting_client", label: "Oczekuje na klienta" },
  { value: "closed", label: "Zamknięty" },
];

export const TicketSearch: React.FC<TicketSearchProps> = ({
  query,
  categoryId,
  status,
  onQueryChange,
  onCategoryChange,
  onStatusChange,
  results,
  isLoading,
  errorMessage,
  onRetry,
  onTicketSelect,
  excludeTicketId,
}) => {
  return (
    <section className="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900/50">
      <header className="flex flex-col gap-1">
        <h2 className="text-lg font-semibold text-slate-900 dark:text-slate-100">
          Wyszukaj istniejący ticket
        </h2>
        <p className="text-sm text-slate-600 dark:text-slate-300">
          Filtruj listę ticketów według tytułu, statusu lub kategorii. Wybierz ticket, aby powiązać
          go z połączeniem.
        </p>
      </header>

      <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
        <label className="flex flex-col gap-1 text-sm">
          <span className="font-medium text-slate-600 dark:text-slate-300">Szukaj</span>
          <input
            type="search"
            value={query}
            onChange={(event) => onQueryChange(event.target.value)}
            placeholder="np. Problemy z połączeniem…"
            className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:focus:border-blue-400 dark:focus:ring-blue-500/20"
          />
        </label>

        <label className="flex flex-col gap-1 text-sm">
          <span className="font-medium text-slate-600 dark:text-slate-300">Kategoria</span>
          <input
            type="text"
            value={categoryId}
            onChange={(event) => onCategoryChange(event.target.value)}
            placeholder="ID kategorii (opcjonalnie)"
            className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:focus:border-blue-400 dark:focus:ring-blue-500/20"
          />
        </label>

        <label className="flex flex-col gap-1 text-sm">
          <span className="font-medium text-slate-600 dark:text-slate-300">Status</span>
          <select
            value={status}
            onChange={(event) => onStatusChange(event.target.value as TicketStatus | "")}
            className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:focus:border-blue-400 dark:focus:ring-blue-500/20"
          >
            <option value="">Dowolny</option>
            {statusOptions.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
        </label>
      </div>

      {errorMessage ? (
        <div className="flex flex-col gap-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-500/40 dark:bg-red-950/40 dark:text-red-200">
          <span>{errorMessage}</span>
          {onRetry ? (
            <button
              type="button"
              onClick={onRetry}
              className="self-start rounded-md border border-red-300 px-2 py-1 text-xs font-semibold text-red-700 transition hover:bg-red-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-400 dark:border-red-500/40 dark:text-red-200 dark:hover:bg-red-800/40"
            >
              Spróbuj ponownie
            </button>
          ) : null}
        </div>
      ) : null}

      <div className="flex flex-col gap-3">
        {isLoading ? (
          <div className="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-900/40 dark:text-slate-300">
            Ładuję listę ticketów…
          </div>
        ) : results.length === 0 ? (
          <div className="rounded-lg border border-dashed border-slate-300 px-4 py-3 text-sm text-slate-600 dark:border-slate-700 dark:text-slate-300">
            Nie znaleziono ticketów spełniających podane kryteria.
          </div>
        ) : (
          <ul className="flex flex-col gap-3">
            {results
              .filter((ticket) => ticket.id !== excludeTicketId)
              .map((ticket) => (
                <li key={ticket.id}>
                  <button
                    type="button"
                    onClick={() => onTicketSelect(ticket)}
                    className="flex w-full flex-col gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 text-left text-sm transition hover:border-blue-400 hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500 dark:border-slate-700 dark:bg-slate-900/40 dark:hover:border-blue-400"
                  >
                    <div className="flex items-center justify-between gap-3">
                      <h3 className="text-base font-semibold text-slate-900 dark:text-slate-100">
                        {ticket.title}
                      </h3>
                      <span className="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-700 dark:bg-blue-900/60 dark:text-blue-200">
                        {ticket.category.name}
                      </span>
                    </div>
                    <div className="flex flex-wrap gap-4 text-xs text-slate-600 dark:text-slate-300">
                      <span>Status: {ticket.status}</span>
                      <span>Klient: {ticket.client.name}</span>
                      <span>Założony: {new Date(ticket.createdAt).toLocaleString()}</span>
                      <span>Czas: {ticket.timeSpent} min</span>
                    </div>
                  </button>
                </li>
              ))}
          </ul>
        )}
      </div>
    </section>
  );
};


