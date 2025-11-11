import * as React from "react";

import type { ScheduledTicket, WorkerBacklogTicket } from "~/api/worker/planning";

export interface TicketCardProps {
  ticket: WorkerBacklogTicket | ScheduledTicket;
  isAssigned?: boolean;
  isSelected?: boolean;
  onSelect?: (ticket: WorkerBacklogTicket | ScheduledTicket) => void;
  actions?: React.ReactNode;
  draggable?: boolean;
  onDragStart?: (ticketId: string) => void;
}

const priorityBadgeClass: Record<string, string> = {
  low: "bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-200",
  normal: "bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300",
  high: "bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300",
  urgent: "bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300",
};

const formatEstimatedTime = (minutes: number) => {
  if (!minutes) {
    return "brak estymacji";
  }
  if (minutes < 60) {
    return `${minutes} min`;
  }
  const hours = Math.floor(minutes / 60);
  const rest = minutes % 60;
  if (rest === 0) {
    return `${hours}h`;
  }
  return `${hours}h ${rest} min`;
};

export const TicketCard: React.FC<TicketCardProps> = ({
  ticket,
  isAssigned,
  isSelected,
  onSelect,
  actions,
  draggable,
  onDragStart,
}) => {
  const isBacklogTicket = "client" in ticket;

  return (
    <article
      className={`rounded-lg border px-4 py-3 text-sm transition ${
        isSelected
          ? "border-emerald-500 bg-emerald-50 dark:border-emerald-400 dark:bg-emerald-900/30"
          : "border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800"
      }`}
      draggable={draggable}
      onDragStart={(event) => {
        if (draggable && onDragStart) {
          event.dataTransfer.setData("text/plain", ticket.id);
          onDragStart(ticket.id);
        }
      }}
      role="listitem"
    >
      <div className="flex items-start justify-between gap-3">
        <div>
          <h3 className="text-base font-semibold text-slate-900 dark:text-slate-100">
            {ticket.title}
          </h3>
          <p className="text-xs text-slate-500 dark:text-slate-400">
            {ticket.category.name}
          </p>
        </div>
        <span
          className={`rounded-full px-2 py-0.5 text-xs font-semibold uppercase ${
            priorityBadgeClass[ticket.priority] ?? priorityBadgeClass.normal
          }`}
        >
          {ticket.priority}
        </span>
      </div>

      <div className="mt-2 flex flex-wrap gap-3 text-xs text-slate-500 dark:text-slate-400">
        <span>Estymacja: {formatEstimatedTime(ticket.estimatedTime)}</span>
        {isBacklogTicket ? (
          <span>Klient: {ticket.client.name}</span>
        ) : null}
        {isBacklogTicket ? (
          <span>
            Status:{" "}
            <span className="font-medium text-slate-600 dark:text-slate-200">
              {ticket.status}
            </span>
          </span>
        ) : null}
      </div>

      {actions ? (
        <div className="mt-3 flex flex-wrap gap-2">{actions}</div>
      ) : null}

      {onSelect ? (
        <button
          type="button"
          onClick={() => onSelect(ticket)}
          className="mt-3 inline-flex items-center text-xs font-medium text-emerald-600 hover:underline focus:outline-none focus-visible:ring focus-visible:ring-emerald-500 dark:text-emerald-300"
        >
          {isAssigned ? "Zobacz szczegóły" : "Wybierz ticket"}
        </button>
      ) : null}
    </article>
  );
};


