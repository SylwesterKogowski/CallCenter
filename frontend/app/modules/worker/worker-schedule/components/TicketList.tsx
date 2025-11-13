import * as React from "react";

import type { ScheduleTicket } from "~/api/worker/schedule";

const formatMinutes = (minutes: number): string => {
  if (!Number.isFinite(minutes) || minutes <= 0) {
    return "0 min";
  }

  const hours = Math.floor(minutes / 60);
  const remainder = minutes % 60;

  if (hours === 0) {
    return `${remainder} min`;
  }

  return remainder > 0 ? `${hours}h ${remainder}min` : `${hours}h`;
};

interface TicketListProps {
  tickets: ScheduleTicket[];
  activeTicketId: string | null;
  onTicketSelect: (ticket: ScheduleTicket) => void;
  onTicketPause: (ticketId: string) => void;
  onTicketClose: (ticketId: string) => void;
  pendingTicketId: string | null;
  closingTicketId: string | null;
  isLoading?: boolean;
}

export const TicketList: React.FC<TicketListProps> = ({
  tickets,
  activeTicketId,
  onTicketSelect,
  onTicketPause,
  onTicketClose,
  pendingTicketId,
  closingTicketId,
  isLoading = false,
}) => {
  const hasTickets = tickets.length > 0;

  return (
    <section className="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-950">
      <div>
        <h2 className="text-lg font-semibold text-slate-900 dark:text-slate-100">
          Zaplanowane tickety
        </h2>
        <p className="text-sm text-slate-500 dark:text-slate-400">
          Wybierz ticket, aby rozpocząć pracę lub zmień jego status.
        </p>
      </div>

      {isLoading && !hasTickets ? (
        <div className="animate-pulse rounded-lg border border-dashed border-slate-200 p-6 text-sm text-slate-500 dark:border-slate-800">
          Ładowanie ticketów...
        </div>
      ) : null}

      <div className="flex flex-col gap-3" data-testid="worker-schedule-ticket-list">
        {tickets.map((ticket) => {
          const isActive = ticket.id === activeTicketId;
          const isPending = ticket.id === pendingTicketId;
          const isClosing = ticket.id === closingTicketId;
          const isClosed = ticket.status === "closed";

          return (
            <article
              key={ticket.id}
              className={[
                "rounded-lg border p-4 transition dark:bg-slate-900/40",
                isActive
                  ? "border-emerald-500 bg-emerald-50/80 dark:border-emerald-500/40 dark:bg-emerald-900/40"
                  : isClosed
                    ? "border-slate-300 bg-slate-100/60 opacity-75 dark:border-slate-700 dark:bg-slate-800/60"
                    : "border-slate-200 bg-white hover:bg-slate-50 dark:border-slate-800",
              ].join(" ")}
              data-testid={`worker-schedule-ticket-${ticket.id}`}
            >
              <div className="flex flex-col gap-2">
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <h3
                      className={[
                        "text-base font-semibold",
                        isClosed
                          ? "text-slate-500 dark:text-slate-400"
                          : "text-slate-900 dark:text-slate-100",
                      ].join(" ")}
                    >
                      {ticket.title}
                    </h3>
                    <p
                      className={[
                        "text-xs uppercase tracking-wide",
                        isClosed
                          ? "text-slate-400 dark:text-slate-500"
                          : "text-slate-500 dark:text-slate-400",
                      ].join(" ")}
                    >
                      {ticket.category.name}
                    </p>
                  </div>
                  <span
                    className={[
                      "rounded-full px-2 py-1 text-xs font-medium",
                      ticket.status === "in_progress"
                        ? "bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200"
                        : ticket.status === "waiting"
                          ? "bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                          : ticket.status === "closed"
                            ? "bg-slate-200 text-slate-500 dark:bg-slate-700 dark:text-slate-400"
                            : "bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-200",
                    ].join(" ")}
                  >
                    {ticket.status}
                  </span>
                </div>

                <dl
                  className={[
                    "grid grid-cols-2 gap-2 text-xs",
                    isClosed
                      ? "text-slate-400 dark:text-slate-500"
                      : "text-slate-500 dark:text-slate-400",
                  ].join(" ")}
                >
                  <div>
                    <dt
                      className={[
                        "font-medium",
                        isClosed
                          ? "text-slate-400 dark:text-slate-500"
                          : "text-slate-600 dark:text-slate-300",
                      ].join(" ")}
                    >
                      Czas spędzony
                    </dt>
                    <dd>{formatMinutes(ticket.timeSpent)}</dd>
                  </div>
                  <div>
                    <dt
                      className={[
                        "font-medium",
                        isClosed
                          ? "text-slate-400 dark:text-slate-500"
                          : "text-slate-600 dark:text-slate-300",
                      ].join(" ")}
                    >
                      Czas zaplanowany
                    </dt>
                    <dd>{formatMinutes(ticket.estimatedTime)}</dd>
                  </div>
                </dl>

                <div className="flex flex-wrap items-center gap-2 pt-1">
                  <button
                    type="button"
                    className="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-indigo-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400 disabled:cursor-not-allowed disabled:bg-indigo-300 dark:bg-indigo-500 dark:hover:bg-indigo-400"
                    onClick={() => onTicketSelect(ticket)}
                    disabled={isPending || isClosing}
                  >
                    {isActive ? "Kontynuuj" : "Rozpocznij"}
                  </button>

                  <button
                    type="button"
                    className="rounded-md border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600 transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-300 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                    onClick={() => onTicketPause(ticket.id)}
                    disabled={isPending || isClosing || ticket.status !== "in_progress"}
                  >
                    Wstrzymaj
                  </button>

                  <button
                    type="button"
                    className="rounded-md border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-medium text-red-700 transition hover:bg-red-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-300 disabled:cursor-not-allowed disabled:opacity-60 dark:border-red-500/40 dark:bg-red-900/40 dark:text-red-200 dark:hover:bg-red-900/60"
                    onClick={() => onTicketClose(ticket.id)}
                    disabled={isPending || isClosing || ticket.status === "closed"}
                  >
                    {isClosing ? "Zamykanie..." : "Zamknij"}
                  </button>
                </div>
              </div>
            </article>
          );
        })}

        {!hasTickets && !isLoading ? (
          <div className="rounded-lg border border-dashed border-slate-200 p-6 text-sm text-slate-500 dark:border-slate-800">
            W tym dniu nie masz zaplanowanych ticketów.
          </div>
        ) : null}
      </div>
    </section>
  );
};


