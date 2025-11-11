import * as React from "react";

import type { ScheduleTicket } from "~/api/worker/schedule";

interface TimeTrackerProps {
  ticket: ScheduleTicket | null;
  trackingStart: number | null;
  onTimeAdd: (
    ticketId: string,
    minutes: number,
    type: "phone_call" | "work",
  ) => Promise<boolean> | boolean;
  isAddingTime: boolean;
  formatMinutes: (minutes: number) => string;
}

export const TimeTracker: React.FC<TimeTrackerProps> = ({
  ticket,
  trackingStart,
  onTimeAdd,
  isAddingTime,
  formatMinutes,
}) => {
  const [now, setNow] = React.useState<number>(() => Date.now());
  const [minutesInput, setMinutesInput] = React.useState<string>("15");
  const [timeType, setTimeType] = React.useState<"phone_call" | "work">("work");

  const isTracking = Boolean(ticket && ticket.status === "in_progress" && trackingStart);

  React.useEffect(() => {
    if (!isTracking) {
      return;
    }

    const intervalId = window.setInterval(() => {
      setNow(Date.now());
    }, 30_000);

    return () => window.clearInterval(intervalId);
  }, [isTracking, ticket?.id]);

  React.useEffect(() => {
    React.startTransition(() => {
      setMinutesInput("15");
      setTimeType("work");
    });
  }, [ticket?.id]);

  if (!ticket) {
    return (
      <section className="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-slate-300 bg-white p-6 text-center text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-300">
        <p>Brak aktywnego ticketa.</p>
        <p>Wybierz ticket, aby rozpocząć monitorowanie czasu.</p>
      </section>
    );
  }

  const elapsedMinutes =
    isTracking && trackingStart
      ? Math.max(0, Math.floor((now - trackingStart) / 60_000))
      : 0;

  const totalMinutes = ticket.timeSpent + elapsedMinutes;

  const handleSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const parsedMinutes = Number.parseInt(minutesInput, 10);
    const success = await onTimeAdd(ticket.id, parsedMinutes, timeType);
    if (success) {
      setMinutesInput("15");
      setTimeType("work");
    }
  };

  return (
    <section
      className="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-950"
      data-testid="worker-schedule-time-tracker"
    >
      <header className="flex flex-col gap-1">
        <h2 className="text-lg font-semibold text-slate-900 dark:text-slate-100">
          Czas pracy
        </h2>
        <p className="text-sm text-slate-500 dark:text-slate-400">
          Monitoruj czas spędzony na aktywnym tickecie i dodawaj czas ręcznie.
        </p>
      </header>

      <div className="rounded-lg border border-slate-200 bg-slate-50 p-4 text-center dark:border-slate-800 dark:bg-slate-900/40">
        <p className="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
          Łącznie
        </p>
        <p className="text-2xl font-semibold text-slate-900 dark:text-slate-100">
          {formatMinutes(totalMinutes)}
        </p>
        {isTracking ? (
          <p className="text-xs text-emerald-600 dark:text-emerald-300">
            Licznik działa — czas dodawany automatycznie
          </p>
        ) : (
          <p className="text-xs text-slate-500 dark:text-slate-400">
            Ticket nie jest aktualnie w statusie „w toku”
          </p>
        )}
      </div>

      <form className="flex flex-col gap-3" onSubmit={handleSubmit}>
        <label
          htmlFor="worker-schedule-time-minutes"
          className="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400"
        >
          Dodaj czas ręcznie
        </label>

        <div className="flex flex-col gap-2 sm:flex-row">
          <input
            id="worker-schedule-time-minutes"
            type="number"
            min={1}
            className="w-full rounded-md border border-slate-200 px-3 py-2 text-sm text-slate-700 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
            value={minutesInput}
            onChange={(event) => setMinutesInput(event.target.value)}
          />
          <select
            className="w-full rounded-md border border-slate-200 px-3 py-2 text-sm text-slate-700 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 sm:max-w-[160px]"
            value={timeType}
            onChange={(event) =>
              setTimeType(event.target.value === "phone_call" ? "phone_call" : "work")
            }
          >
            <option value="work">Praca nad ticketem</option>
            <option value="phone_call">Rozmowa telefoniczna</option>
          </select>
        </div>

        <div className="flex justify-end">
          <button
            type="submit"
            className="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-indigo-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400 disabled:cursor-not-allowed disabled:bg-indigo-300 dark:bg-indigo-500 dark:hover:bg-indigo-400"
            disabled={isAddingTime}
          >
            {isAddingTime ? "Zapisywanie..." : "Dodaj czas"}
          </button>
        </div>
      </form>
    </section>
  );
};


