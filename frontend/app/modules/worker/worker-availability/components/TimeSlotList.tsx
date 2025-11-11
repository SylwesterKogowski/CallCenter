import * as React from "react";

import type { TimeSlot } from "~/api/worker/availability";

import type { ValidationError } from "../types";
import { sortTimeSlots } from "../utils";

export interface TimeSlotListProps {
  timeSlots: TimeSlot[];
  onEdit: (timeSlotId: string) => void;
  onRemove: (timeSlotId: string) => Promise<boolean> | boolean;
  validationErrors: ValidationError[];
  isRemoving?: boolean;
}

export const TimeSlotList: React.FC<TimeSlotListProps> = ({
  timeSlots,
  onEdit,
  onRemove,
  validationErrors,
  isRemoving = false,
}) => {
  if (timeSlots.length === 0) {
    return (
      <p className="rounded-md border border-dashed border-slate-300 p-4 text-sm text-slate-500 dark:border-slate-700 dark:text-slate-300">
        Nie dodano jeszcze żadnych przedziałów czasowych dla tego dnia.
      </p>
    );
  }

  const sortedTimeSlots = sortTimeSlots(timeSlots);

  const getErrorsForSlot = (timeSlotId: string) =>
    validationErrors.filter(
      (error) => error.timeSlotId === timeSlotId || error.timeSlotId === null,
    );

  return (
    <ul className="flex flex-col gap-3">
      {sortedTimeSlots.map((slot) => {
        const slotErrors = getErrorsForSlot(slot.id);
        const hasOverlapError = slotErrors.some((error) => error.field === "overlap");

        return (
          <li
            key={slot.id}
            className={[
              "flex items-center justify-between gap-4 rounded-lg border px-4 py-3 transition",
              hasOverlapError
                ? "border-red-200 bg-red-50 dark:border-red-500/40 dark:bg-red-950/40"
                : "border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900",
            ].join(" ")}
          >
            <div>
              <p className="text-sm font-medium text-slate-900 dark:text-slate-100">
                {slot.startTime} - {slot.endTime}
              </p>

              {slotErrors.length > 0 ? (
                <ul className="mt-1 space-y-1 text-xs text-red-600 dark:text-red-400">
                  {slotErrors.map((error) => (
                    <li
                      key={`${error.field}-${error.timeSlotId ?? "day"}-${error.message}`}
                    >
                      {error.message}
                    </li>
                  ))}
                </ul>
              ) : null}
            </div>

            <div className="flex gap-2">
              <button
                type="button"
                onClick={() => onEdit(slot.id)}
                className="rounded-md border border-slate-200 px-2 py-1 text-xs font-medium text-slate-600 transition hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800/60"
              >
                Edytuj
              </button>
              <button
                type="button"
                disabled={isRemoving}
                onClick={() => {
                  void onRemove(slot.id);
                }}
                className="rounded-md border border-red-200 px-2 py-1 text-xs font-medium text-red-600 transition hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-300 disabled:cursor-not-allowed disabled:opacity-75 dark:border-red-500/40 dark:text-red-300 dark:hover:bg-red-900/40"
              >
                Usuń
              </button>
            </div>
          </li>
        );
      })}
    </ul>
  );
};


