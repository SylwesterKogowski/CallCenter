import * as React from "react";

import type { TimeSlotPayload } from "~/api/worker/availability";

import type { ValidationError } from "../types";

export interface TimeSlotFormProps {
  timeSlot: TimeSlotPayload | null;
  onSave: (timeSlot: TimeSlotPayload) => Promise<void> | void;
  onCancel: () => void;
  errors: ValidationError[];
  isSubmitting?: boolean;
}

export const TimeSlotForm: React.FC<TimeSlotFormProps> = ({
  timeSlot,
  onSave,
  onCancel,
  errors,
  isSubmitting = false,
}) => {
  const [formState, setFormState] = React.useState<TimeSlotPayload>(() => {
    return (
      timeSlot ?? {
        startTime: "09:00",
        endTime: "17:00",
      }
    );
  });

  const handleChange = React.useCallback((event: React.ChangeEvent<HTMLInputElement>) => {
    const { name, value } = event.target;
    setFormState((current) => ({
      ...current,
      [name]: value,
    }));
  }, []);

  const handleSubmit = React.useCallback(
    async (event: React.FormEvent<HTMLFormElement>) => {
      event.preventDefault();
      await onSave(formState);
    },
    [formState, onSave],
  );

  const startErrors = errors.filter((error) => error.field === "startTime");
  const endErrors = errors.filter((error) => error.field === "endTime");
  const orderErrors = errors.filter((error) => error.field === "order");
  const overlapErrors = errors.filter((error) => error.field === "overlap");

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-3 rounded-lg border p-4">
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <label className="flex flex-col gap-1 text-sm font-medium text-slate-600 dark:text-slate-200">
          Godzina rozpoczęcia
          <input
            type="time"
            name="startTime"
            value={formState.startTime}
            onChange={handleChange}
            className="rounded-md border border-slate-200 px-3 py-2 text-sm text-slate-900 shadow-sm transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
          />
          {startErrors.map((error) => (
            <span key={`${error.field}-${error.message}`} className="text-xs text-red-600">
              {error.message}
            </span>
          ))}
        </label>

        <label className="flex flex-col gap-1 text-sm font-medium text-slate-600 dark:text-slate-200">
          Godzina zakończenia
          <input
            type="time"
            name="endTime"
            value={formState.endTime}
            onChange={handleChange}
            className="rounded-md border border-slate-200 px-3 py-2 text-sm text-slate-900 shadow-sm transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
          />
          {endErrors.map((error) => (
            <span key={`${error.field}-${error.message}`} className="text-xs text-red-600">
              {error.message}
            </span>
          ))}
        </label>
      </div>

      {orderErrors.map((error) => (
        <p key={`${error.field}-${error.message}`} className="text-xs text-red-600">
          {error.message}
        </p>
      ))}

      {overlapErrors.map((error) => (
        <p key={`${error.field}-${error.message}`} className="text-xs text-red-600">
          {error.message}
        </p>
      ))}

      <div className="flex flex-wrap gap-2">
        <button
          type="submit"
          className="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white transition hover:bg-indigo-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400 disabled:cursor-not-allowed disabled:bg-indigo-400"
          disabled={isSubmitting}
        >
          {isSubmitting ? "Zapisywanie..." : "Zapisz przedział"}
        </button>
        <button
          type="button"
          className="inline-flex items-center rounded-md border border-slate-200 px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800/60"
          onClick={onCancel}
        >
          Anuluj
        </button>
      </div>
    </form>
  );
};


