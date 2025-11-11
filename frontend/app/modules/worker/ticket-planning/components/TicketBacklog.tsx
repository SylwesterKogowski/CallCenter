import * as React from "react";

import type { WorkerBacklogTicket } from "~/api/worker/planning";

import { TicketCard } from "./TicketCard";

export interface TicketBacklogProps {
  tickets: WorkerBacklogTicket[];
  sortBy: string;
  selectedDay: string | null;
  selectedTicketId: string | null;
  isLoading?: boolean;
  error?: string | null;
  onRetry?: () => void;
  onTicketSelect?: (ticket: WorkerBacklogTicket) => void;
  onTicketAssign: (ticket: WorkerBacklogTicket, day: string) => void;
}

export const TicketBacklog: React.FC<TicketBacklogProps> = ({
  tickets,
  sortBy,
  selectedDay,
  selectedTicketId,
  isLoading,
  error,
  onRetry,
  onTicketSelect,
  onTicketAssign,
}) => {
  return (
    <div className="flex h-full flex-col rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
      <div className="border-b border-slate-200 px-4 py-3 dark:border-slate-800">
        <div className="flex items-center justify-between gap-3">
          <div>
            <h2 className="text-lg font-semibold text-slate-900 dark:text-slate-100">
              Backlog ticketów
            </h2>
            <p className="text-xs text-slate-500 dark:text-slate-400">
              Sortowanie: <span className="font-medium">{sortBy}</span>
            </p>
          </div>
          {selectedDay ? (
            <span className="rounded-full bg-emerald-100 px-3 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300">
              Wybrany dzień: {selectedDay}
            </span>
          ) : (
            <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">
              Wybierz dzień z grafika
            </span>
          )}
        </div>
      </div>

      <div className="flex-1 overflow-y-auto px-4 py-3">
        {isLoading ? (
          <p className="text-sm text-slate-500 dark:text-slate-400">Ładowanie backlogu...</p>
        ) : null}

        {error ? (
          <div className="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-600 dark:border-red-400/30 dark:bg-red-900/30 dark:text-red-300">
            <p>Nie udało się pobrać backlogu ticketów.</p>
            <p className="mt-1 text-xs opacity-80">{error}</p>
            {onRetry ? (
              <button
                type="button"
                className="mt-2 inline-flex items-center rounded-md border border-red-200 px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-100 focus:outline-none focus-visible:ring focus-visible:ring-red-400 dark:border-red-400/30 dark:hover:bg-red-900/50"
                onClick={onRetry}
              >
                Spróbuj ponownie
              </button>
            ) : null}
          </div>
        ) : null}

        {!isLoading && !error && tickets.length === 0 ? (
          <p className="text-sm text-slate-500 dark:text-slate-400">
            Brak ticketów w backlogu dopasowanych do aktywnych filtrów.
          </p>
        ) : null}

        <div className="mt-2 flex flex-col gap-3" role="list">
          {tickets.map((ticket) => (
            <TicketCard
              key={ticket.id}
              ticket={ticket}
              isSelected={ticket.id === selectedTicketId}
              onSelect={onTicketSelect}
              draggable
              onDragStart={() => {
                if (!selectedDay) {
                  return;
                }
              }}
              actions={
                selectedDay ? (
                  <button
                    type="button"
                    className="inline-flex items-center rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-emerald-700 focus:outline-none focus-visible:ring focus-visible:ring-emerald-500 disabled:cursor-not-allowed disabled:bg-emerald-300"
                    onClick={() => onTicketAssign(ticket, selectedDay)}
                  >
                    Przypisz do {selectedDay}
                  </button>
                ) : (
                  <span className="text-xs text-slate-500">Wybierz dzień w grafiku</span>
                )
              }
            />
          ))}
        </div>
      </div>
    </div>
  );
};


