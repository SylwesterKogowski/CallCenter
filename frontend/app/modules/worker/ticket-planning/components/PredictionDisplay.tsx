import * as React from "react";

import type { DayPrediction, WeekScheduleDay } from "~/api/worker/planning";

export interface PredictionDisplayProps {
  predictions: DayPrediction[];
  weekSchedule: WeekScheduleDay[];
  isLoading?: boolean;
}

const formatDateLabel = (date: string) => {
  const parsedDate = new Date(date);
  return parsedDate.toLocaleDateString("pl-PL", {
    weekday: "short",
    day: "2-digit",
    month: "2-digit",
  });
};

const minuteToHours = (minutes: number) => {
  if (minutes <= 0) {
    return "0h";
  }

  const hours = Math.floor(minutes / 60);
  const restMinutes = minutes % 60;

  if (hours === 0) {
    return `${restMinutes} min`;
  }

  if (restMinutes === 0) {
    return `${hours}h`;
  }

  return `${hours}h ${restMinutes} min`;
};

export const PredictionDisplay: React.FC<PredictionDisplayProps> = ({
  predictions,
  weekSchedule,
  isLoading,
}) => {
  if (isLoading) {
    return (
      <div className="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300">
        Ładowanie przewidywań obciążenia...
      </div>
    );
  }

  if (predictions.length === 0) {
    return (
      <div className="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300">
        Brak dostępnych przewidywań dla nadchodzących dni.
      </div>
    );
  }

  const scheduleMap = weekSchedule.reduce<Record<string, WeekScheduleDay>>((map, day) => {
    map[day.date] = day;
    return map;
  }, {});

  return (
    <div className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
      <div className="border-b border-slate-200 bg-slate-50 px-4 py-2 text-sm font-medium text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
        Przewidywana ilość ticketów i obłożenie
      </div>
      <ul className="divide-y divide-slate-200 dark:divide-slate-800">
        {predictions.map((prediction) => {
          const daySchedule = scheduleMap[prediction.date];
          const assignedTickets = daySchedule?.tickets.length ?? 0;
          const difference = prediction.predictedTicketCount - assignedTickets;
          const isPositive = difference >= 0;

          return (
            <li
              key={prediction.date}
              className="flex flex-col gap-2 px-4 py-3 text-sm text-slate-700 dark:text-slate-200 sm:flex-row sm:items-center sm:justify-between"
            >
              <div className="flex min-w-[160px] flex-col sm:flex-row sm:items-center sm:gap-2">
                <span className="font-medium">{formatDateLabel(prediction.date)}</span>
                <span className="text-xs text-slate-500 dark:text-slate-400">
                  Dostępny czas: {minuteToHours(prediction.availableTime)}
                </span>
              </div>
              <div className="flex flex-wrap items-center gap-3">
                <span className="rounded-md bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                  Przewidywane: {prediction.predictedTicketCount}
                </span>
                <span className="rounded-md bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                  Przypisane: {assignedTickets}
                </span>
                <span
                  className={`rounded-md px-2 py-1 text-xs font-semibold ${
                    isPositive
                      ? "bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300"
                      : "bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300"
                  }`}
                >
                  {isPositive ? "+" : ""}
                  {difference} różnicy
                </span>
                <div className="flex items-center gap-1 text-xs text-slate-500 dark:text-slate-400">
                  Efektywność:{" "}
                  <span className="font-medium text-slate-700 dark:text-slate-200">
                    {(prediction.efficiency * 100).toFixed(0)}%
                  </span>
                </div>
              </div>
            </li>
          );
        })}
      </ul>
    </div>
  );
};


