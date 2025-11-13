import * as React from "react";

import type { ScheduleDay } from "~/api/worker/schedule";

const dateFormatter = new Intl.DateTimeFormat("pl-PL", {
  weekday: "long",
  day: "numeric",
  month: "long",
});

const shortDateFormatter = new Intl.DateTimeFormat("pl-PL", {
  weekday: "short",
});

const hasActiveTicket = (day: ScheduleDay, activeTicketId: string | null) => {
  if (!activeTicketId) {
    return false;
  }
  return day.tickets.some((ticket) => ticket.id === activeTicketId);
};

const pluralizeTickets = (count: number): string => {
  if (count === 1) {
    return "ticket";
  }
  if (count >= 2 && count <= 4) {
    return "tickety";
  }
  return "ticketów";
};

interface ScheduleCalendarProps {
  schedule: ScheduleDay[];
  selectedDate: string | null;
  onSelectDate: (date: string) => void;
  activeTicketId: string | null;
  isLoading?: boolean;
}

export const ScheduleCalendar: React.FC<ScheduleCalendarProps> = ({
  schedule,
  selectedDate,
  onSelectDate,
  activeTicketId,
  isLoading = false,
}) => {
  return (
    <section className="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-950">
      <div>
        <h2 className="text-lg font-semibold text-slate-900 dark:text-slate-100">
          Nadchodzące dyżury
        </h2>
        <p className="text-sm text-slate-500 dark:text-slate-400">
          Wybierz dzień, aby zobaczyć zaplanowane tickety i rozpocząć pracę.
        </p>
      </div>

      {isLoading && schedule.length === 0 ? (
        <div className="animate-pulse rounded-lg border border-dashed border-slate-200 p-6 text-sm text-slate-500 dark:border-slate-800">
          Ładowanie grafika...
        </div>
      ) : null}

      <div
        className="grid gap-3 sm:grid-cols-2"
        data-testid="worker-schedule-calendar"
      >
        {schedule.map((day) => {
          const formattedDate = dateFormatter.format(new Date(`${day.date}T00:00:00`));
          const shortWeekday = shortDateFormatter.format(new Date(`${day.date}T00:00:00`));
          const ticketCount = day.tickets.length;
          const isSelected = selectedDate === day.date;
          const active = hasActiveTicket(day, activeTicketId);

          return (
            <button
              key={day.date}
              type="button"
              onClick={() => onSelectDate(day.date)}
              data-testid={`worker-schedule-calendar-day-${day.date}`}
              className={[
                "flex flex-col gap-3 rounded-lg border p-4 text-left transition focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-950",
                isSelected
                  ? "border-indigo-500 bg-indigo-50/80 dark:border-indigo-500/50 dark:bg-indigo-900/40"
                  : "border-slate-200 bg-white hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900/40 dark:hover:bg-slate-900/60",
              ].join(" ")}
            >
              <div className="flex items-center justify-between">
                <span className="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium uppercase text-slate-600 dark:bg-slate-800 dark:text-slate-200">
                  {shortWeekday}
                </span>
                {active ? (
                  <span className="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-900/60 dark:text-emerald-200">
                    <span className="h-2 w-2 rounded-full bg-emerald-500" aria-hidden="true" />
                    Ticket w toku
                  </span>
                ) : null}
              </div>
              <div>
                <p className="text-base font-semibold text-slate-900 dark:text-slate-100">
                  {formattedDate}
                </p>
                <p className="text-sm text-slate-500 dark:text-slate-400">
                  {ticketCount} {pluralizeTickets(ticketCount)}
                </p>
              </div>
              <div className="flex flex-col gap-1 text-xs text-slate-500 dark:text-slate-400">
                <div className="flex items-center justify-between">
                  <span>Zaplanowany czas</span>
                  <span>{day.totalTimePlanned} min</span>
                </div>
                <div className="flex items-center justify-between">
                  <span>Dostępny czas</span>
                  <span>{day.totalAvailableTime} min</span>
                </div>
              </div>
            </button>
          );
        })}

        {schedule.length === 0 && !isLoading ? (
          <div className="rounded-lg border border-dashed border-slate-200 p-6 text-sm text-slate-500 dark:border-slate-800">
            Brak zaplanowanych ticketów. Sprawdź później lub skontaktuj się z managerem.
          </div>
        ) : null}
      </div>
    </section>
  );
};


