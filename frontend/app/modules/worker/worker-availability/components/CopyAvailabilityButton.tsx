import * as React from "react";

import type { DayAvailability } from "~/api/worker/availability";

export interface CopyAvailabilityButtonProps {
  days: DayAvailability[];
  onCopy: (sourceDate: string, targetDates: string[], overwrite: boolean) => void;
  isCopying?: boolean;
}

export const CopyAvailabilityButton: React.FC<CopyAvailabilityButtonProps> = ({
  days,
  onCopy,
  isCopying = false,
}) => {
  const [isOpen, setIsOpen] = React.useState(false);
  const [sourceDate, setSourceDate] = React.useState<string>(() => days[0]?.date ?? "");
  const [targetDates, setTargetDates] = React.useState<string[]>([]);
  const [overwrite, setOverwrite] = React.useState<boolean>(false);
  const dialogTitleId = React.useId();
  const dialogDescriptionId = React.useId();

  const hasSourceDate = React.useMemo(
    () => days.some((day) => day.date === sourceDate),
    [days, sourceDate],
  );

  const safeSourceDate = React.useMemo(() => {
    if (hasSourceDate) {
      return sourceDate;
    }

    return days[0]?.date ?? "";
  }, [days, hasSourceDate, sourceDate]);

  const handleSubmit = (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!safeSourceDate || targetDates.length === 0) {
      return;
    }
    onCopy(safeSourceDate, targetDates, overwrite);
    setIsOpen(false);
    setTargetDates([]);
  };

  const toggleTargetDate = (date: string) => {
    setTargetDates((current) =>
      current.includes(date) ? current.filter((value) => value !== date) : [...current, date],
    );
  };

  return (
    <div className="relative">
      <button
        type="button"
        onClick={() => setIsOpen((current) => !current)}
        className="inline-flex items-center rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800/60"
      >
        Kopiuj dostępność
      </button>

      {isOpen ? (
        <form
          onSubmit={handleSubmit}
          className="absolute right-0 z-20 mt-2 w-80 rounded-lg border border-slate-200 bg-white p-4 shadow-lg dark:border-slate-700 dark:bg-slate-900"
          role="dialog"
          aria-modal="true"
          aria-labelledby={dialogTitleId}
          aria-describedby={dialogDescriptionId}
        >
          <div className="flex items-start justify-between gap-2">
            <p id={dialogTitleId} className="text-sm font-semibold text-slate-900 dark:text-slate-100">
              Kopiuj dostępność
            </p>
            <button
              type="button"
              onClick={() => setIsOpen(false)}
              className="text-xs text-slate-500 transition hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200"
            >
              Zamknij
            </button>
          </div>
          <p id={dialogDescriptionId} className="mt-1 text-xs text-slate-500 dark:text-slate-300">
            Wybierz dzień źródłowy i zaznacz dni docelowe, na które chcesz skopiować dostępność.
          </p>

          <label className="mt-3 block text-xs font-medium text-slate-500 dark:text-slate-300">
            Dzień źródłowy
            <select
              value={safeSourceDate}
              onChange={(event) => setSourceDate(event.target.value)}
              className="mt-1 w-full rounded-md border border-slate-200 px-3 py-2 text-sm text-slate-800 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
            >
              {days.map((day) => (
                <option key={day.date} value={day.date}>
                  {day.date} ({day.timeSlots.length > 0 ? `${day.timeSlots.length} przedziały` : "brak"})
                </option>
              ))}
            </select>
          </label>

          <fieldset className="mt-3">
            <legend className="text-xs font-medium text-slate-500 dark:text-slate-300">
              Dni docelowe
            </legend>
            <div className="mt-2 flex flex-col gap-2">
              {days
                .filter((day) => day.date !== sourceDate)
                .map((day) => (
                  <label key={day.date} className="flex items-center gap-2 text-xs text-slate-600 dark:text-slate-200">
                    <input
                      type="checkbox"
                      value={day.date}
                      checked={targetDates.includes(day.date)}
                      onChange={() => toggleTargetDate(day.date)}
                      className="h-3 w-3 rounded border border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900"
                    />
                    <span>{day.date}</span>
                    {day.timeSlots.length > 0 ? (
                      <span className="text-[10px] text-slate-400">
                        {day.timeSlots.map((slot) => `${slot.startTime}-${slot.endTime}`).join(", ")}
                      </span>
                    ) : null}
                  </label>
                ))}
            </div>
          </fieldset>

          <label className="mt-3 flex items-center gap-2 text-xs text-slate-600 dark:text-slate-200">
            <input
              type="checkbox"
              checked={overwrite}
              onChange={(event) => setOverwrite(event.target.checked)}
              className="h-3 w-3 rounded border border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900"
            />
            Nadpisz istniejącą dostępność
          </label>

          <button
            type="submit"
            disabled={isCopying}
            className="mt-4 inline-flex w-full items-center justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400 disabled:cursor-not-allowed disabled:bg-indigo-300"
          >
            {isCopying ? "Kopiowanie..." : "Kopiuj"}
          </button>
        </form>
      ) : null}
    </div>
  );
};


