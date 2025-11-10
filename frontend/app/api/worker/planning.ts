// Plik odpowiedzialny za hooki planowania ticketów przez pracownika (backlog, grafik tygodniowy, auto-przydział).

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { apiFetch, apiPaths } from "../http";
import type { ApiMutationOptions, ApiQueryOptions } from "../react-query";
import type {
  Client,
  TicketCategory,
  TicketPriority,
  TicketStatus,
} from "../types";

export interface WorkerBacklogTicket {
  id: string;
  title: string;
  category: TicketCategory & { defaultResolutionTime: number };
  status: TicketStatus;
  priority: TicketPriority;
  client: Client;
  estimatedTime: number;
  createdAt: string;
  scheduledDate: string | null;
}

export interface WorkerBacklogFilters {
  categories?: string[];
  statuses?: TicketStatus[];
  priorities?: TicketPriority[];
  search?: string;
  sort?: "created_at" | "priority" | "category" | "estimated_time";
}

export interface WorkerBacklogResponse {
  tickets: WorkerBacklogTicket[];
  total: number;
}

export const workerBacklogRootKey = ["worker", "planning", "backlog"] as const;

const workerBacklogKey = (filters: WorkerBacklogFilters = {}) =>
  [...workerBacklogRootKey, filters] as const;

export const useWorkerBacklogQuery = (
  filters: WorkerBacklogFilters,
  options?: ApiQueryOptions<WorkerBacklogResponse, ReturnType<typeof workerBacklogKey>>,
) => {
  return useQuery({
    queryKey: workerBacklogKey(filters),
    queryFn: () =>
      apiFetch<WorkerBacklogResponse>({
        path: apiPaths.workerTicketsBacklog,
        params: {
          categories: filters.categories,
          statuses: filters.statuses,
          priorities: filters.priorities,
          search: filters.search,
          sort: filters.sort,
        },
      }),
    ...options,
  });
};

export interface AvailabilitySlot {
  startTime: string;
  endTime: string;
}

export interface ScheduledTicket {
  id: string;
  title: string;
  category: TicketCategory;
  estimatedTime: number;
  priority: TicketPriority;
}

export interface WeekScheduleDay {
  date: string;
  isAvailable: boolean;
  availabilityHours: AvailabilitySlot[];
  tickets: ScheduledTicket[];
  totalEstimatedTime: number;
}

export interface WorkerWeekScheduleResponse {
  schedule: WeekScheduleDay[];
}

export const workerWeekScheduleKey = ["worker", "planning", "weekSchedule"] as const;

export const useWorkerWeekScheduleQuery = (
  options?: ApiQueryOptions<
    WorkerWeekScheduleResponse,
    typeof workerWeekScheduleKey
  >,
) => {
  return useQuery({
    queryKey: workerWeekScheduleKey,
    queryFn: () =>
      apiFetch<WorkerWeekScheduleResponse>({
        path: apiPaths.workerScheduleWeek,
      }),
    ...options,
  });
};

export interface DayPrediction {
  date: string;
  predictedTicketCount: number;
  availableTime: number;
  efficiency: number;
}

export interface WorkerPredictionsResponse {
  predictions: DayPrediction[];
}

export const workerPredictionsKey = ["worker", "planning", "predictions"] as const;

export const useWorkerPredictionsQuery = (
  options?: ApiQueryOptions<
    WorkerPredictionsResponse,
    typeof workerPredictionsKey
  >,
) => {
  return useQuery({
    queryKey: workerPredictionsKey,
    queryFn: () =>
      apiFetch<WorkerPredictionsResponse>({
        path: apiPaths.workerSchedulePredictions,
      }),
    ...options,
  });
};

export interface AssignTicketPayload {
  ticketId: string;
  date: string;
}

export interface AssignTicketResponse {
  assignment: {
    ticketId: string;
    date: string;
    assignedAt: string;
  };
}

const planningQueryKeysToInvalidate = [
  workerBacklogRootKey,
  workerWeekScheduleKey,
  workerPredictionsKey,
] as const;

export const useAssignTicketMutation = (
  options?: ApiMutationOptions<AssignTicketResponse, AssignTicketPayload>,
) => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload) =>
      apiFetch<AssignTicketResponse>({
        path: apiPaths.workerScheduleAssign,
        method: "POST",
        body: payload,
      }),
    onSuccess: (data, variables, context, mutation) => {
      planningQueryKeysToInvalidate.forEach((key) =>
        queryClient.invalidateQueries({ queryKey: key }),
      );
      options?.onSuccess?.(data, variables, context, mutation);
    },
    ...options,
  });
};

export interface UnassignTicketPayload {
  ticketId: string;
  date: string;
}

export interface UnassignTicketResponse {
  success: boolean;
}

export const useUnassignTicketMutation = (
  options?: ApiMutationOptions<UnassignTicketResponse, UnassignTicketPayload>,
) => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload) =>
      apiFetch<UnassignTicketResponse>({
        path: apiPaths.workerScheduleAssign,
        method: "DELETE",
        body: payload,
      }),
    onSuccess: (data, variables, context, mutation) => {
      planningQueryKeysToInvalidate.forEach((key) =>
        queryClient.invalidateQueries({ queryKey: key }),
      );
      options?.onSuccess?.(data, variables, context, mutation);
    },
    ...options,
  });
};

export interface AutoAssignPayload {
  weekStartDate: string;
  categories?: string[];
}

export interface AutoAssignResponse {
  assignments: Array<{
    ticketId: string;
    date: string;
  }>;
  totalAssigned: number;
}

export const useAutoAssignTicketsMutation = (
  options?: ApiMutationOptions<AutoAssignResponse, AutoAssignPayload>,
) => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload) =>
      apiFetch<AutoAssignResponse>({
        path: apiPaths.workerScheduleAutoAssign,
        method: "POST",
        body: payload,
      }),
    onSuccess: (data, variables, context, mutation) => {
      planningQueryKeysToInvalidate.forEach((key) =>
        queryClient.invalidateQueries({ queryKey: key }),
      );
      options?.onSuccess?.(data, variables, context, mutation);
    },
    ...options,
  });
};


