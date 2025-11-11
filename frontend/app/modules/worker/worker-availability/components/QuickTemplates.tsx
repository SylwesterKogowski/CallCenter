import * as React from "react";

import type { TimeSlotPayload } from "~/api/worker/availability";

import type { ValidationError } from "../types";

export interface TimeSlotTemplate {
  id: string;
  name: string;
  timeSlots: TimeSlotPayload[];
}

const defaultTemplates: TimeSlotTemplate[] = [
  {
    id: "classic-9-17",
    name: "Standard 9:00-17:00",
    timeSlots: [
      {
        startTime: "09:00",
        endTime: "17:00",
      },
    ],
  },
  {
    id: "split-shift",
    name: "Zmienny 9-13 & 14-18",
    timeSlots: [
      {
        startTime: "09:00",
        endTime: "13:00",
      },
      {
        startTime: "14:00",
        endTime: "18:00",
      },
    ],
  },
  {
    id: "early-bird",
    name: "Poranek 7:00-15:00",
    timeSlots: [
      {
        startTime: "07:00",
        endTime: "15:00",
      },
    ],
  },
];

export interface QuickTemplatesProps {
  selectedDate: string | null;
  allDates: string[];
  onTemplateApply: (template: TimeSlotTemplate, targetDates: string[]) => void;
  validationErrors?: ValidationError[];
  isApplying?: boolean;
}

export const QuickTemplates: React.FC<QuickTemplatesProps> = ({
  selectedDate,
  allDates,
  onTemplateApply,
  isApplying = false,
}) => {
  const [customTemplate, setCustomTemplate] = React.useState({
    name: "",
    startTime: "09:00",
    endTime: "17:00",
  });

  const templates = React.useMemo(() => defaultTemplates, []);

  const handleCustomTemplateSubmit = (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    if (!customTemplate.name.trim()) {
      return;
    }

    onTemplateApply(
      {
        id: `custom-${Date.now()}`,
        name: customTemplate.name.trim(),
        timeSlots: [
          {
            startTime: customTemplate.startTime,
            endTime: customTemplate.endTime,
          },
        ],
      },
      selectedDate ? [selectedDate] : allDates,
    );

    setCustomTemplate({
      name: "",
      startTime: customTemplate.startTime,
      endTime: customTemplate.endTime,
    });
  };

  return (
    <section className="flex flex-col gap-4 rounded-lg border border-slate-200 p-4 dark:border-slate-800">
      <header className="flex flex-col gap-1">
        <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100">
          Szybkie szablony
        </h2>
        <p className="text-sm text-slate-500 dark:text-slate-300">
          Zastosuj przygotowany szablon godzin dla wybranego albo wszystkich dni.
        </p>
      </header>

      <div className="grid gap-3 sm:grid-cols-2">
        {templates.map((template) => (
          <div
            key={template.id}
            className="flex flex-col gap-3 rounded-lg border border-slate-200 p-3 dark:border-slate-800"
          >
            <div>
              <p className="text-sm font-semibold text-slate-800 dark:text-slate-100">
                {template.name}
              </p>
              <p className="text-xs text-slate-500 dark:text-slate-300">
                {template.timeSlots.map((slot) => `${slot.startTime}-${slot.endTime}`).join(", ")}
              </p>
            </div>
            <div className="flex flex-wrap gap-2">
              <button
                type="button"
                disabled={!selectedDate || isApplying}
                className="inline-flex items-center rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-indigo-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400 disabled:cursor-not-allowed disabled:bg-indigo-300"
                onClick={() => {
                  if (selectedDate) {
                    onTemplateApply(template, [selectedDate]);
                  }
                }}
              >
                Zastosuj dla dnia
              </button>
              <button
                type="button"
                disabled={allDates.length === 0 || isApplying}
                className="inline-flex items-center rounded-md border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800/60"
                onClick={() => onTemplateApply(template, allDates)}
              >
                Zastosuj dla tygodnia
              </button>
            </div>
          </div>
        ))}
      </div>

      <form
        className="flex flex-col gap-3 rounded-lg border border-dashed border-slate-300 p-3 dark:border-slate-700"
        onSubmit={handleCustomTemplateSubmit}
      >
        <p className="text-sm font-semibold text-slate-700 dark:text-slate-200">
          Własny szablon
        </p>
        <label className="text-xs font-medium text-slate-500 dark:text-slate-300">
          Nazwa szablonu
          <input
            type="text"
            value={customTemplate.name}
            onChange={(event) =>
              setCustomTemplate((current) => ({ ...current, name: event.target.value }))
            }
            className="mt-1 w-full rounded-md border border-slate-200 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
            placeholder="Np. Popołudnie 12-20"
          />
        </label>
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <label className="text-xs font-medium text-slate-500 dark:text-slate-300">
            Start
            <input
              type="time"
              value={customTemplate.startTime}
              onChange={(event) =>
                setCustomTemplate((current) => ({ ...current, startTime: event.target.value }))
              }
              className="mt-1 w-full rounded-md border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
            />
          </label>
          <label className="text-xs font-medium text-slate-500 dark:text-slate-300">
            Koniec
            <input
              type="time"
              value={customTemplate.endTime}
              onChange={(event) =>
                setCustomTemplate((current) => ({ ...current, endTime: event.target.value }))
              }
              className="mt-1 w-full rounded-md border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
            />
          </label>
        </div>
        <button
          type="submit"
          disabled={isApplying}
          className="inline-flex items-center justify-center rounded-md border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800/60"
        >
          Utwórz i zastosuj
        </button>
      </form>
    </section>
  );
};


