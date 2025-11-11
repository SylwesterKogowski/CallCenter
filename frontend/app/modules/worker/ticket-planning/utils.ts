import type { WorkerBacklogFilters, WeekScheduleDay } from "~/api/worker/planning";

import type { TicketPlanningFilters } from "./TicketPlanning";

export interface BuildFiltersPayloadInput extends TicketPlanningFilters {
  sortBy: NonNullable<WorkerBacklogFilters["sort"]>;
}

export const buildFiltersPayload = (
  input: BuildFiltersPayloadInput,
): WorkerBacklogFilters => ({
  categories: input.categories.length > 0 ? input.categories : undefined,
  statuses: input.statuses.length > 0 ? input.statuses : undefined,
  priorities: input.priorities.length > 0 ? input.priorities : undefined,
  search: input.searchQuery ? input.searchQuery : undefined,
  sort: input.sortBy,
});

export const getWeekStartDate = (schedule: WeekScheduleDay[]): string | null => {
  if (!schedule.length) {
    return null;
  }

  const sorted = [...schedule].sort((a, b) => (a.date < b.date ? -1 : 1));
  return sorted[0]?.date ?? null;
};


