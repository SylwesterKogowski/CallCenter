import * as React from "react";

import type { DayAvailability } from "~/api/worker/availability";

import { getDayLabel } from "../utils";

export interface AvailabilityCalendarProps {
  availability: DayAvailability[];
  selectedDay: string | null;
  onDaySelect: (date: string) => void;
  onDayEdit?: (date: string) => void;
  isLoading?: boolean;
}

export const AvailabilityCalendar: React.FC<AvailabilityCalendarProps> = ({
  availability,
  selectedDay,
  onDaySelect,
  onDayEdit,
  isLoading = false,
}) => {
  const today = React.useMemo(() => new Date().toISOString().slice(0, 10), []);
  const placeholderDates = React.useMemo(() => {
    const base = new Date();
    base.setHours(0, 0, 0, 0);

    return Array.from({ length: 7 }, (_, offset) => {
      const date = new Date(base);
      date.setDate(base.getDate() + offset);
      return date.toISOString();
    });
  }, []);

  if (isLoading) {
    return (
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
        {placeholderDates.map((date) => (
          <div key={date} className="animate-pulse rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900/60">
            <div className="h-4 w-2/3 rounded bg-slate-200 dark:bg-slate-700" />
            <div className="mt-4 h-3 w-full rounded bg-slate-200 dark:bg-slate-700" />
            <div className="mt-2 h-3 w-3/4 rounded bg-slate-200 dark:bg-slate-700" />
          </div>
        ))}
      </div>
    );
  }

  if (availability.length === 0) {
    return (
      <p className="rounded-md border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-900/50 dark:text-slate-300">
        Brak danych o dostępności. Spróbuj ponownie później.
      </p>
    );
  }

  return (
    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
      {availability.map((day) => {
        const isSelected = day.date === selectedDay;
        const isToday = day.date === today;
        const hasAvailability = day.timeSlots.length > 0;

        return (
          <button
            key={day.date}
            type="button"
            className={[
              "group flex flex-col gap-2 rounded-lg border p-4 text-left transition",
              isSelected
                ? "border-indigo-500 bg-indigo-50 dark:border-indigo-400/60 dark:bg-indigo-500/10"
                : "border-slate-200 bg-white hover:border-indigo-300 hover:bg-indigo-50/60 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-indigo-500/40 dark:hover:bg-indigo-900/40",
            ].join(" ")}
            onClick={() => onDaySelect(day.date)}
            aria-pressed={isSelected}
          >
            <div className="flex items-center justify-between gap-2">
              <div>
                <p className="text-sm font-medium text-slate-500 dark:text-slate-300">
                  {getDayLabel(day.date)}
                </p>
                {isToday ? (
                  <span className="mt-1 inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                    Dzisiaj
                  </span>
                ) : null}
              </div>

              <div className="text-right text-sm text-slate-500 dark:text-slate-300">
                <span className="font-semibold text-slate-900 dark:text-slate-100">
                  {day.totalHours.toLocaleString("pl-PL", {
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 2,
                  })}
                </span>{" "}
                h
              </div>
            </div>

            <div className="flex flex-wrap gap-2">
              {hasAvailability ? (
                day.timeSlots.map((slot) => (
                  <span
                    key={slot.id}
                    className="inline-flex items-center rounded-md border border-indigo-200 bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700 dark:border-indigo-500/40 dark:bg-indigo-900/40 dark:text-indigo-200"
                  >
                    {slot.startTime} - {slot.endTime}
                  </span>
                ))
              ) : (
                <span className="text-xs text-slate-400 dark:text-slate-500">
                  Brak dostępności
                </span>
              )}
            </div>

            {onDayEdit ? (
              <div className="mt-2 flex">
                <span className="text-xs text-indigo-600 underline-offset-2 group-hover:underline dark:text-indigo-300">
                  Edytuj dostępność
                </span>
              </div>
            ) : null}
          </button>
        );
      })}
    </div>
  );
};


