import * as React from "react";

import type { WorkerTicket } from "~/api/worker/phone";

interface TicketDisplayProps {
  ticket: WorkerTicket | null;
  onTicketChange: () => void;
  onTicketCreate: () => void;
}

const statusLabels: Record<string, string> = {
  waiting: "Oczekujący",
  in_progress: "W toku",
  completed: "Zakończony",
  awaiting_response: "Oczekuje na odpowiedź",
  awaiting_client: "Oczekuje na klienta",
  closed: "Zamknięty",
};

export const TicketDisplay: React.FC<TicketDisplayProps> = ({
  ticket,
  onTicketChange,
  onTicketCreate,
}) => {
  if (!ticket) {
    return (
      <div className="flex flex-col gap-3 rounded-xl border border-dashed border-slate-300 p-4 text-sm text-slate-600 dark:border-slate-700 dark:text-slate-300">
        <p>Nie wybrano jeszcze ticketa. Wybierz istniejący lub utwórz nowy.</p>
        <div className="flex flex-wrap gap-2">
          <button
            type="button"
            className="rounded-lg border border-blue-500 px-3 py-1 text-sm font-medium text-blue-600 transition hover:bg-blue-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500 dark:border-blue-400 dark:text-blue-300"
            onClick={onTicketChange}
          >
            Wyszukaj ticketa
          </button>
          <button
            type="button"
            className="rounded-lg border border-emerald-500 px-3 py-1 text-sm font-medium text-emerald-600 transition hover:bg-emerald-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-500 dark:border-emerald-400 dark:text-emerald-300"
            onClick={onTicketCreate}
          >
            Utwórz nowy ticket
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-900/40">
      <div className="flex flex-col gap-1">
        <span className="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
          Wybrany ticket
        </span>
        <h3 className="text-lg font-semibold text-slate-900 dark:text-slate-100">{ticket.title}</h3>
      </div>
      <dl className="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
        <div className="flex flex-col">
          <dt className="font-medium text-slate-500 dark:text-slate-400">Kategoria</dt>
          <dd className="text-slate-900 dark:text-slate-100">{ticket.category.name}</dd>
        </div>
        <div className="flex flex-col">
          <dt className="font-medium text-slate-500 dark:text-slate-400">Status</dt>
          <dd className="text-slate-900 dark:text-slate-100">
            {statusLabels[ticket.status] ?? ticket.status}
          </dd>
        </div>
        <div className="flex flex-col">
          <dt className="font-medium text-slate-500 dark:text-slate-400">Klient</dt>
          <dd className="text-slate-900 dark:text-slate-100">{ticket.client.name}</dd>
        </div>
        <div className="flex flex-col">
          <dt className="font-medium text-slate-500 dark:text-slate-400">Czas przed rozmową</dt>
          <dd className="text-slate-900 dark:text-slate-100">{ticket.timeSpent} min</dd>
        </div>
      </dl>

      <div className="flex flex-wrap gap-2">
        <button
          type="button"
          className="rounded-lg border border-blue-500 px-3 py-1 text-sm font-medium text-blue-600 transition hover:bg-blue-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500 dark:border-blue-400 dark:text-blue-300"
          onClick={onTicketChange}
        >
          Zmień ticket
        </button>
        <button
          type="button"
          className="rounded-lg border border-emerald-500 px-3 py-1 text-sm font-medium text-emerald-600 transition hover:bg-emerald-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-500 dark:border-emerald-400 dark:text-emerald-300"
          onClick={onTicketCreate}
        >
          Utwórz nowy
        </button>
      </div>
    </div>
  );
};


