import * as React from "react";

import type { TimeSlot } from "~/api/worker/availability";

import type { ValidationError } from "../types";
import { sortTimeSlots } from "../utils";

export interface AvailabilityTimelineProps {
  timeSlots: TimeSlot[];
  validationErrors: ValidationError[];
}

const MINUTES_IN_DAY = 24 * 60;

const toMinutes = (time: string): number | null => {
  const match = /^([01]\d|2[0-3]):([0-5]\d)$/.exec(time);
  if (!match) {
    return null;
  }
  const [, hours, minutes] = match;
  return Number(hours) * 60 + Number(minutes);
};

export const AvailabilityTimeline: React.FC<AvailabilityTimelineProps> = ({
  timeSlots,
  validationErrors,
}) => {
  const slots = sortTimeSlots(timeSlots);

  if (slots.length === 0) {
    return null;
  }

  return (
    <div className="flex flex-col gap-3 rounded-lg border border-slate-200 p-4 dark:border-slate-800">
      <p className="text-sm font-medium text-slate-600 dark:text-slate-200">Oś czasu</p>
      <div className="relative h-16 rounded-md bg-slate-100 dark:bg-slate-900">
        <div className="absolute inset-0 grid grid-cols-6 text-[10px] text-slate-500 dark:text-slate-400">
          {Array.from({ length: 6 }).map((_, index) => {
            const hour = index * 4;
            return (
              <div key={hour} className="flex h-full flex-col justify-between">
                <span>{String(hour).padStart(2, "0")}:00</span>
                <span>{String(hour + 4).padStart(2, "0")}:00</span>
              </div>
            );
          })}
        </div>

        {slots.map((slot) => {
          const start = toMinutes(slot.startTime);
          const end = toMinutes(slot.endTime);

          if (start === null || end === null || end <= start) {
            return null;
          }

          const left = (start / MINUTES_IN_DAY) * 100;
          const width = ((end - start) / MINUTES_IN_DAY) * 100;
          const hasError = validationErrors.some(
            (error) => error.timeSlotId === slot.id && error.field === "overlap",
          );

          return (
            <div
              key={slot.id}
              className={[
                "absolute top-1/3 flex h-6 items-center justify-center rounded-md text-[10px] font-semibold text-white shadow-sm",
                hasError ? "bg-red-500" : "bg-indigo-500",
              ].join(" ")}
              style={{
                left: `${left}%`,
                width: `${width}%`,
                minWidth: "3rem",
              }}
              title={`${slot.startTime} - ${slot.endTime}`}
            >
              {slot.startTime} - {slot.endTime}
            </div>
          );
        })}
      </div>
    </div>
  );
};


