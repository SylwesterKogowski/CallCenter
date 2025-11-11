import * as React from "react";

import { useTicketCategoriesQuery } from "~/api/ticket-categories";
import type { TicketCategory, TicketPriority, TicketStatus } from "~/api/types";
import {
  type AssignTicketPayload,
  type DayPrediction,
  type WeekScheduleDay,
  type WorkerBacklogFilters,
  type WorkerBacklogTicket,
  useAssignTicketMutation,
  useAutoAssignTicketsMutation,
  useUnassignTicketMutation,
  useWorkerBacklogQuery,
  useWorkerPredictionsQuery,
  useWorkerWeekScheduleQuery,
} from "~/api/worker/planning";

import { AutoAssignButton } from "./components/AutoAssignButton";
import { PredictionDisplay } from "./components/PredictionDisplay";
import { TicketBacklog } from "./components/TicketBacklog";
import { TicketFiltersPanel } from "./components/TicketFiltersPanel";
import { WeekScheduleView } from "./components/WeekScheduleView";
import { buildFiltersPayload, getWeekStartDate } from "./utils";

const resolveErrorMessage = (error: unknown): string | null => {
  if (!error) {
    return null;
  }

  if (error instanceof Error) {
    return error.message;
  }

  if (typeof error === "string") {
    return error;
  }

  return null;
};

export interface TicketPlanningProps {
  workerId: string;
}

export interface TicketPlanningFilters {
  categories: string[];
  statuses: TicketStatus[];
  priorities: TicketPriority[];
  searchQuery: string;
}

const defaultFilters: TicketPlanningFilters = {
  categories: [],
  statuses: [],
  priorities: [],
  searchQuery: "",
};

export type TicketPlanningSortOption = NonNullable<WorkerBacklogFilters["sort"]>;

const defaultSort: TicketPlanningSortOption = "priority";

export const TicketPlanning: React.FC<TicketPlanningProps> = () => {
  const [filters, setFilters] = React.useState<TicketPlanningFilters>(defaultFilters);
  const [sortBy, setSortBy] = React.useState<TicketPlanningSortOption>(defaultSort);
  const [selectedDay, setSelectedDay] = React.useState<string | null>(null);
  const [selectedTicketId, setSelectedTicketId] = React.useState<string | null>(null);

  const backlogQuery = useWorkerBacklogQuery(
    React.useMemo(
      () =>
        buildFiltersPayload({
          ...filters,
          sortBy,
        }),
      [filters, sortBy],
    ),
    {
      staleTime: 30_000,
    },
  );

  const weekScheduleQuery = useWorkerWeekScheduleQuery({
    staleTime: 30_000,
  });

  const predictionsQuery = useWorkerPredictionsQuery({
    staleTime: 60_000,
  });

  const ticketCategoriesQuery = useTicketCategoriesQuery({
    staleTime: 300_000,
  });

  const assignTicketMutation = useAssignTicketMutation();
  const unassignTicketMutation = useUnassignTicketMutation();
  const autoAssignTicketsMutation = useAutoAssignTicketsMutation();

  const handleFiltersChange = React.useCallback((nextFilters: TicketPlanningFilters) => {
    setFilters(nextFilters);
  }, []);

  const handleSortChange = React.useCallback((nextSort: TicketPlanningSortOption) => {
    setSortBy(nextSort);
  }, []);

  const handleDaySelect = React.useCallback((day: string) => {
    setSelectedDay(day);
  }, []);

  const handleTicketSelect = React.useCallback((ticket: WorkerBacklogTicket) => {
    setSelectedTicketId((current) => (current === ticket.id ? null : ticket.id));
  }, []);

  const handleAssignTicket = React.useCallback(
    (payload: AssignTicketPayload) => {
      assignTicketMutation.mutate(payload);
    },
    [assignTicketMutation],
  );

  const handleUnassignTicket = React.useCallback(
    (ticketId: string, date: string) => {
      unassignTicketMutation.mutate({ ticketId, date });
    },
    [unassignTicketMutation],
  );

  const schedule: WeekScheduleDay[] = weekScheduleQuery.data?.schedule ?? [];
  const predictions: DayPrediction[] = predictionsQuery.data?.predictions ?? [];
  const tickets: WorkerBacklogTicket[] = backlogQuery.data?.tickets ?? [];
  const categories: TicketCategory[] = ticketCategoriesQuery.data?.categories ?? [];

  const effectiveSelectedDay = selectedDay ?? schedule[0]?.date ?? null;
  const weekStartDate = React.useMemo(() => getWeekStartDate(schedule), [schedule]);

  const isLoading =
    backlogQuery.isLoading ||
    weekScheduleQuery.isLoading ||
    predictionsQuery.isLoading ||
    ticketCategoriesQuery.isLoading;

  const hasError =
    backlogQuery.isError ||
    weekScheduleQuery.isError ||
    predictionsQuery.isError ||
    ticketCategoriesQuery.isError;

  const errorMessage =
    resolveErrorMessage(backlogQuery.error) ??
    resolveErrorMessage(weekScheduleQuery.error) ??
    resolveErrorMessage(predictionsQuery.error) ??
    resolveErrorMessage(ticketCategoriesQuery.error) ??
    null;

  return (
    <div className="flex flex-col gap-6">
      <header className="flex flex-col gap-2">
        <h1 className="text-2xl font-semibold text-slate-900 dark:text-slate-100">
          Planowanie ticketów
        </h1>
        <p className="text-sm text-slate-600 dark:text-slate-300">
          Zarządzaj backlogiem ticketów i przypisuj je do nadchodzącego tygodnia, korzystając z
          przewidywań obciążenia i swojej dostępności.
        </p>
      </header>

      <TicketFiltersPanel
        filters={filters}
        sortBy={sortBy}
        availableCategories={categories}
        onFiltersChange={handleFiltersChange}
        onSortChange={handleSortChange}
        isLoading={ticketCategoriesQuery.isLoading}
        error={ticketCategoriesQuery.isError
          ? resolveErrorMessage(ticketCategoriesQuery.error)
          : null}
        onRetry={ticketCategoriesQuery.refetch}
      />

      <div className="flex flex-col gap-6 lg:flex-row">
        <section className="w-full lg:w-1/3">
          <TicketBacklog
            tickets={tickets}
            isLoading={backlogQuery.isLoading}
            error={backlogQuery.isError ? resolveErrorMessage(backlogQuery.error) : null}
            sortBy={sortBy}
            selectedDay={effectiveSelectedDay}
            selectedTicketId={selectedTicketId}
            onTicketSelect={handleTicketSelect}
            onTicketAssign={(ticket, day) => handleAssignTicket({ ticketId: ticket.id, date: day })}
            onRetry={backlogQuery.refetch}
          />
        </section>

        <section className="w-full flex-1">
          <WeekScheduleView
            weekSchedule={schedule}
            predictions={predictions}
            isLoading={weekScheduleQuery.isLoading || predictionsQuery.isLoading}
            selectedDay={effectiveSelectedDay}
            onDaySelect={handleDaySelect}
            onTicketAssign={(ticketId, date) => handleAssignTicket({ ticketId, date })}
            onTicketUnassign={handleUnassignTicket}
          />
        </section>
      </div>

      <PredictionDisplay predictions={predictions} weekSchedule={schedule} isLoading={isLoading} />

      <div className="flex justify-end">
        <AutoAssignButton
          predictions={predictions}
          isLoading={autoAssignTicketsMutation.isLoading}
          onAutoAssign={() => {
            if (!weekStartDate) {
              return;
            }

            autoAssignTicketsMutation.mutate({
              weekStartDate,
              categories: filters.categories.length > 0 ? filters.categories : undefined,
            });
          }}
          disabled={!weekStartDate}
        />
      </div>

      {isLoading ? (
        <p className="text-sm text-slate-500" role="status">
          Ładowanie danych...
        </p>
      ) : null}

      {hasError ? (
        <div className="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-400/40 dark:bg-red-950/40 dark:text-red-300">
          <p className="font-medium">Nie udało się pobrać części danych modułu planowania.</p>
          {errorMessage ? <p className="mt-1 text-xs opacity-80">{errorMessage}</p> : null}
          <div className="mt-3 flex flex-wrap gap-2 text-xs">
            <button
              type="button"
              className="rounded-md border border-red-200 bg-white px-2 py-1 font-medium text-red-700 transition hover:bg-red-100 focus:outline-none focus-visible:ring focus-visible:ring-red-400 dark:border-red-400/40 dark:bg-red-900 dark:text-red-200 dark:hover:bg-red-800"
              onClick={() => {
                backlogQuery.refetch();
                weekScheduleQuery.refetch();
                predictionsQuery.refetch();
                ticketCategoriesQuery.refetch();
              }}
            >
              Odśwież dane
            </button>
          </div>
        </div>
      ) : null}
    </div>
  );
};


