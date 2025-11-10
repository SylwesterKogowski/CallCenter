import * as React from "react";

import type { TicketChatDetails } from "../types";

export interface TicketStatusDisplayProps {
  ticket: TicketChatDetails;
}

const STATUS_LABELS: Record<string, string> = {
  awaiting_response: "Oczekuje na odpowiedz",
  in_progress: "W toku",
  awaiting_client: "Oczekuje na klienta",
  closed: "Zamkniety",
  waiting: "W kolejce",
  completed: "Zakonczony",
};

const STATUS_COLORS: Record<string, string> = {
  awaiting_response: "bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200",
  in_progress: "bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200",
  awaiting_client: "bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200",
  closed: "bg-slate-200 text-slate-900 dark:bg-slate-800 dark:text-slate-100",
  waiting: "bg-slate-100 text-slate-700 dark:bg-slate-900 dark:text-slate-200",
  completed: "bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200",
};

const formatDate = (value: string | undefined) => {
  if (!value) {
    return null;
  }

  try {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
      return null;
    }

    return new Intl.DateTimeFormat("pl-PL", {
      dateStyle: "medium",
      timeStyle: "short",
    }).format(date);
  } catch {
    return null;
  }
};

export const TicketStatusDisplay: React.FC<TicketStatusDisplayProps> = ({ ticket }) => {
  const statusLabel = STATUS_LABELS[ticket.status] ?? ticket.status;
  const statusClassName = STATUS_COLORS[ticket.status] ?? STATUS_COLORS.waiting;
  const createdAt = formatDate(ticket.createdAt);
  const updatedAt = formatDate(ticket.updatedAt);

  return (
    <section
      aria-label="Informacje o tickecie"
      className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900"
    >
      <header className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="text-lg font-semibold text-slate-900 dark:text-slate-100">Ticket #{ticket.id}</h2>
          {ticket.title ? (
            <p className="text-sm text-slate-600 dark:text-slate-300">{ticket.title}</p>
          ) : null}
        </div>
        <span
          className={`inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold ${statusClassName}`}
          aria-live="polite"
        >
          Status: {statusLabel}
        </span>
      </header>

      <dl className="mt-4 grid gap-3 text-sm text-slate-600 dark:text-slate-300 sm:grid-cols-2">
        <div>
          <dt className="font-medium text-slate-700 dark:text-slate-200">Kategoria</dt>
          <dd>{ticket.categoryName}</dd>
        </div>
        {ticket.description ? (
          <div>
            <dt className="font-medium text-slate-700 dark:text-slate-200">Opis</dt>
            <dd className="whitespace-pre-line leading-relaxed">{ticket.description}</dd>
          </div>
        ) : null}
        {createdAt ? (
          <div>
            <dt className="font-medium text-slate-700 dark:text-slate-200">Utworzony</dt>
            <dd>{createdAt}</dd>
          </div>
        ) : null}
        {updatedAt ? (
          <div>
            <dt className="font-medium text-slate-700 dark:text-slate-200">Ostatnia aktualizacja</dt>
            <dd>{updatedAt}</dd>
          </div>
        ) : null}
      </dl>
    </section>
  );
};
