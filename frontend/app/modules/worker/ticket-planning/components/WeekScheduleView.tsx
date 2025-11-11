import * as React from "react";

import type { DayPrediction, WeekScheduleDay } from "~/api/worker/planning";

import { DayColumn } from "./DayColumn";

export interface WeekScheduleViewProps {
  weekSchedule: WeekScheduleDay[];
  predictions: DayPrediction[];
  selectedDay: string | null;
  isLoading?: boolean;
  onDaySelect: (date: string) => void;
  onTicketAssign?: (ticketId: string, date: string) => void;
  onTicketUnassign: (ticketId: string, date: string) => void;
}

const isToday = (date: string) => {
  const today = new Date();
  const target = new Date(date);
  return (
    today.getFullYear() === target.getFullYear() &&
    today.getMonth() === target.getMonth() &&
    today.getDate() === target.getDate()
  );
};

export const WeekScheduleView: React.FC<WeekScheduleViewProps> = ({
  weekSchedule,
  predictions,
  selectedDay,
  isLoading,
  onDaySelect,
  onTicketAssign,
  onTicketUnassign,
}) => {
  const predictionsMap = React.useMemo(
    () =>
      predictions.reduce<Record<string, DayPrediction>>((map, prediction) => {
        map[prediction.date] = prediction;
        return map;
      }, {}),
    [predictions],
  );

  if (isLoading) {
    return (
      <div className="rounded-xl border border-slate-200 bg-white px-4 py-6 text-center text-sm text-slate-500 shadow-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300">
        Ładowanie grafika tygodniowego...
      </div>
    );
  }

  if (weekSchedule.length === 0) {
    return (
      <div className="rounded-xl border border-slate-200 bg-white px-4 py-6 text-center text-sm text-slate-500 shadow-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300">
        Brak danych grafika dla najbliższych dni.
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-4">
      <div>
        <h2 className="text-lg font-semibold text-slate-900 dark:text-slate-100">
          Grafik tygodniowy
        </h2>
        <p className="text-xs text-slate-500 dark:text-slate-400">
          Przeciągnij ticket z backlogu lub wybierz dzień, w którym chcesz go przypisać. Kliknij
          ticket w kolumnie, aby usunąć przypisanie.
        </p>
      </div>

      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        {weekSchedule.map((day) => (
          <DayColumn
            key={day.date}
            day={day}
            prediction={predictionsMap[day.date]}
            isSelected={selectedDay === day.date}
            isToday={isToday(day.date)}
            onSelect={() => onDaySelect(day.date)}
            onTicketDrop={(ticketId) =>
              onTicketAssign ? onTicketAssign(ticketId, day.date) : undefined
            }
            onTicketRemove={(ticketId) => onTicketUnassign(ticketId, day.date)}
          />
        ))}
      </div>
    </div>
  );
};


