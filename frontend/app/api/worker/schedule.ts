// Plik odpowiedzialny za hooki obsługujące bieżący grafik pracownika oraz jego status pracy.

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { apiFetch, apiPaths } from "../http";
import type { ApiMutationOptions, ApiQueryOptions } from "../react-query";
import type {
  Client,
  TicketCategory,
  TicketNote,
  TicketStatus,
} from "../types";

export interface ScheduleTicket {
  id: string;
  title: string;
  category: TicketCategory;
  status: TicketStatus;
  timeSpent: number;
  estimatedTime: number;
  scheduledDate: string;
  isActive?: boolean;
  notes?: TicketNote[];
  client?: Client;
}

export interface ScheduleDay {
  date: string;
  tickets: ScheduleTicket[];
  totalTimeSpent: number;
}

export interface WorkerScheduleResponse {
  schedule: ScheduleDay[];
  activeTicket: ScheduleTicket | null;
}

export const workerScheduleKey = ["worker", "schedule", "current"] as const;

export const useWorkerScheduleQuery = (
  options?: ApiQueryOptions<WorkerScheduleResponse, typeof workerScheduleKey>,
) => {
  return useQuery({
    queryKey: workerScheduleKey,
    queryFn: () =>
      apiFetch<WorkerScheduleResponse>({
        path: apiPaths.workerSchedule,
      }),
    ...options,
  });
};

export interface WorkStatus {
  level: "low" | "normal" | "high" | "critical";
  message: string;
  ticketsCount: number;
  timeSpent: number;
  timePlanned: number;
}

export interface DayStats {
  date: string;
  ticketsCount: number;
  timeSpent: number;
  timePlanned: number;
  completedTickets: number;
  inProgressTickets: number;
  waitingTickets: number;
}

export interface WorkerWorkStatusResponse {
  status: WorkStatus;
  todayStats: DayStats;
}

export const workerWorkStatusKey = ["worker", "schedule", "workStatus"] as const;

export const useWorkerWorkStatusQuery = (
  options?: ApiQueryOptions<
    WorkerWorkStatusResponse,
    typeof workerWorkStatusKey
  >,
) => {
  return useQuery({
    queryKey: workerWorkStatusKey,
    queryFn: () =>
      apiFetch<WorkerWorkStatusResponse>({
        path: apiPaths.workerWorkStatus,
      }),
    ...options,
  });
};

const invalidateScheduleData = (
  queryClient: ReturnType<typeof useQueryClient>,
) => {
  queryClient.invalidateQueries({ queryKey: workerScheduleKey });
  queryClient.invalidateQueries({ queryKey: workerWorkStatusKey });
};

export interface UpdateTicketStatusPayload {
  ticketId: string;
  status: TicketStatus;
}

export interface UpdateTicketStatusResponse {
  ticket: {
    id: string;
    status: TicketStatus;
    updatedAt: string;
  };
}

export const useUpdateTicketStatusMutation = (
  options?: ApiMutationOptions<
    UpdateTicketStatusResponse,
    UpdateTicketStatusPayload
  >,
) => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ ticketId, status }) =>
      apiFetch<UpdateTicketStatusResponse>({
        path: apiPaths.workerTicketStatus(ticketId),
        method: "POST",
        body: { status },
      }),
    onSuccess: (data, variables, context, mutation) => {
      invalidateScheduleData(queryClient);
      options?.onSuccess?.(data, variables, context, mutation);
    },
    ...options,
  });
};

export interface AddTicketTimePayload {
  ticketId: string;
  minutes: number;
  type: "phone_call" | "work";
}

export interface AddTicketTimeResponse {
  ticket: {
    id: string;
    timeSpent: number;
    updatedAt: string;
  };
}

export const useAddTicketTimeMutation = (
  options?: ApiMutationOptions<AddTicketTimeResponse, AddTicketTimePayload>,
) => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ ticketId, ...payload }) =>
      apiFetch<AddTicketTimeResponse>({
        path: apiPaths.workerTicketTime(ticketId),
        method: "POST",
        body: payload,
      }),
    onSuccess: (data, variables, context, mutation) => {
      invalidateScheduleData(queryClient);
      options?.onSuccess?.(data, variables, context, mutation);
    },
    ...options,
  });
};

export interface AddScheduleTicketNotePayload {
  ticketId: string;
  content: string;
}

export const useAddScheduleTicketNoteMutation = (
  options?: ApiMutationOptions<
    { note: TicketNote },
    AddScheduleTicketNotePayload
  >,
) => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ ticketId, content }) =>
      apiFetch<{ note: TicketNote }>({
        path: apiPaths.workerTicketNotes(ticketId),
        method: "POST",
        body: { content },
      }),
    onSuccess: (data, variables, context, mutation) => {
      invalidateScheduleData(queryClient);
      options?.onSuccess?.(data, variables, context, mutation);
    },
    ...options,
  });
};


