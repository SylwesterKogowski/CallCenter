import * as React from "react";

import type { DayPrediction, WeekScheduleDay } from "~/api/worker/planning";

import { TicketCard } from "./TicketCard";

export interface DayColumnProps {
  day: WeekScheduleDay;
  prediction?: DayPrediction;
  isSelected: boolean;
  isToday: boolean;
  onSelect: () => void;
  onTicketDrop: (ticketId: string) => void;
  onTicketRemove: (ticketId: string) => void;
}

const formatDateHeader = (date: string) => {
  const parsedDate = new Date(date);
  return parsedDate.toLocaleDateString("pl-PL", {
    weekday: "long",
    day: "2-digit",
    month: "long",
  });
};

const availabilityLabel = (availability: WeekScheduleDay["availabilityHours"]) => {
  if (!availability.length) {
    return "Brak dostępności";
  }

  return availability
    .map((slot) => `${slot.startTime} - ${slot.endTime}`)
    .join(", ");
};

const calculateRemainingTime = (day: WeekScheduleDay, prediction?: DayPrediction) => {
  if (!prediction) {
    return null;
  }
  const remaining = prediction.availableTime - day.totalEstimatedTime;
  return remaining;
};

export const DayColumn: React.FC<DayColumnProps> = ({
  day,
  prediction,
  isSelected,
  isToday,
  onSelect,
  onTicketDrop,
  onTicketRemove,
}) => {
  const [isDragOver, setIsDragOver] = React.useState(false);

  return (
    <div
      className={`flex h-full flex-col rounded-xl border p-4 transition ${
        isSelected
          ? "border-emerald-500 bg-emerald-50 dark:border-emerald-400 dark:bg-emerald-900/40"
          : "border-slate-200 bg-white hover:border-emerald-300 dark:border-slate-700 dark:bg-slate-800"
      } ${isDragOver ? "ring-2 ring-emerald-400" : ""}`}
      onClick={onSelect}
      onDragOver={(event) => {
        event.preventDefault();
        if (!day.isAvailable) {
          return;
        }
        setIsDragOver(true);
      }}
      onDragLeave={() => setIsDragOver(false)}
      onDrop={(event) => {
        event.preventDefault();
        setIsDragOver(false);

        if (!day.isAvailable) {
          return;
        }

        const ticketId = event.dataTransfer.getData("text/plain");
        if (ticketId) {
          onTicketDrop(ticketId);
        }
      }}
      role="group"
    >
      <header className="flex flex-col gap-1 border-b border-slate-200 pb-3 dark:border-slate-700">
        <div className="flex items-center justify-between text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
          <span>{formatDateHeader(day.date)}</span>
          {isToday ? (
            <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300">
              Dzisiaj
            </span>
          ) : null}
        </div>

        <p className="text-sm text-slate-600 dark:text-slate-300">
          {day.isAvailable ? availabilityLabel(day.availabilityHours) : "Brak dostępności"}
        </p>

        {prediction ? (
          <div className="flex flex-wrap items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
            <span>Przewidywane: {prediction.predictedTicketCount} ticketów</span>
            <span>Dostępny czas: {prediction.availableTime} min</span>
            <span>Efektywność: {(prediction.efficiency * 100).toFixed(0)}%</span>
          </div>
        ) : null}

        {prediction ? (
          <p
            className={`text-xs font-semibold ${
              calculateRemainingTime(day, prediction) ?? 0 >= 0
                ? "text-emerald-600"
                : "text-red-600"
            }`}
          >
            Pozostało: {calculateRemainingTime(day, prediction) ?? 0} min
          </p>
        ) : null}
      </header>

      <div className="mt-3 flex-1 space-y-2">
        {day.tickets.length === 0 ? (
          <p className="rounded-md border border-dashed border-slate-300 px-3 py-6 text-center text-xs text-slate-500 dark:border-slate-600 dark:text-slate-400">
            {day.isAvailable
              ? "Przeciągnij ticket tutaj lub wybierz z backlogu"
              : "Dzień bez dostępności"}
          </p>
        ) : null}

        {day.tickets.map((ticket) => (
          <TicketCard
            key={ticket.id}
            ticket={ticket}
            isAssigned
            actions={
              <button
                type="button"
                className="inline-flex items-center rounded-md border border-red-200 px-2 py-1 text-xs font-semibold text-red-600 hover:bg-red-50 focus:outline-none focus-visible:ring focus-visible:ring-red-400 dark:border-red-400/30 dark:text-red-300 dark:hover:bg-red-900/30"
                onClick={(event) => {
                  event.stopPropagation();
                  onTicketRemove(ticket.id);
                }}
              >
                Usuń z dnia
              </button>
            }
          />
        ))}
      </div>
    </div>
  );
};


