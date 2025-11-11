import type { DayAvailability, TimeSlot, TimeSlotPayload } from "~/api/worker/availability";

import type { ValidationError } from "./types";

const TIME_REGEX = /^([01]\d|2[0-3]):([0-5]\d)$/;

const getMinutes = (time: string): number | null => {
  if (!TIME_REGEX.test(time)) {
    return null;
  }

  const [hours, minutes] = time.split(":").map(Number);

  if (Number.isNaN(hours) || Number.isNaN(minutes)) {
    return null;
  }

  return hours * 60 + minutes;
};

export const sortTimeSlots = <T extends TimeSlot | TimeSlotPayload>(timeSlots: T[]): T[] => {
  return [...timeSlots].sort((a, b) => {
    const minutesA = getMinutes(a.startTime) ?? 0;
    const minutesB = getMinutes(b.startTime) ?? 0;

    return minutesA - minutesB;
  });
};

export const calculateTotalHours = (
  timeSlots: Array<Pick<TimeSlot, "startTime" | "endTime">>,
): number => {
  const totalMinutes = timeSlots.reduce((sum, slot) => {
    const start = getMinutes(slot.startTime);
    const end = getMinutes(slot.endTime);

    if (start === null || end === null || end <= start) {
      return sum;
    }

    return sum + (end - start);
  }, 0);

  return Math.round((totalMinutes / 60) * 100) / 100;
};

export const validateTimeSlots = (
  timeSlots: Array<Pick<TimeSlot, "id" | "startTime" | "endTime">>,
): ValidationError[] => {
  const errors: ValidationError[] = [];
  const seen: Array<{ id: string | null; start: number; end: number }> = [];

  for (const slot of timeSlots) {
    const startMinutes = getMinutes(slot.startTime);
    const endMinutes = getMinutes(slot.endTime);

    if (startMinutes === null) {
      errors.push({
        timeSlotId: slot.id ?? null,
        field: "startTime",
        message: "Godzina rozpoczęcia musi być w formacie HH:mm (00:00-23:59).",
      });
    }

    if (endMinutes === null) {
      errors.push({
        timeSlotId: slot.id ?? null,
        field: "endTime",
        message: "Godzina zakończenia musi być w formacie HH:mm (00:00-23:59).",
      });
    }

    if (startMinutes !== null && endMinutes !== null && endMinutes <= startMinutes) {
      errors.push({
        timeSlotId: slot.id ?? null,
        field: "order",
        message: "Godzina zakończenia musi być późniejsza niż godzina rozpoczęcia.",
      });
    }

    if (startMinutes !== null && endMinutes !== null) {
      seen.push({
        id: slot.id ?? null,
        start: startMinutes,
        end: endMinutes,
      });
    }
  }

  const sorted = seen.sort((a, b) => a.start - b.start);

  for (let index = 1; index < sorted.length; index += 1) {
    const current = sorted[index]!;
    const previous = sorted[index - 1]!;

    if (current.start < previous.end) {
      errors.push({
        timeSlotId: current.id,
        field: "overlap",
        message: "Przedziały czasowe nie mogą się nakładać.",
      });
      errors.push({
        timeSlotId: previous.id,
        field: "overlap",
        message: "Przedziały czasowe nie mogą się nakładać.",
      });
    }
  }

  return errors;
};

export const upsertDayAvailability = (
  availability: DayAvailability[],
  updatedDay: DayAvailability,
): DayAvailability[] => {
  const next = availability.filter((day) => day.date !== updatedDay.date);

  return sortDaysByDate([...next, updatedDay]);
};

export const sortDaysByDate = (days: DayAvailability[]): DayAvailability[] => {
  return [...days].sort((a, b) => new Date(a.date).getTime() - new Date(b.date).getTime());
};

export const buildEmptyDayAvailability = (date: string): DayAvailability => ({
  date,
  timeSlots: [],
  totalHours: 0,
});

export const getDayLabel = (date: string, locale: string = "pl-PL"): string => {
  const formatter = new Intl.DateTimeFormat(locale, {
    weekday: "long",
    day: "numeric",
    month: "long",
  });

  return formatter.format(new Date(date));
};


