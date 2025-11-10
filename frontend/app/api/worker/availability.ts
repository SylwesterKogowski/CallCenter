// Plik odpowiedzialny za hooki obsługujące dostępności pracownika (pobieranie, modyfikacje, kopiowanie).

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { apiFetch, apiPaths } from "../http";
import type { ApiMutationOptions, ApiQueryOptions } from "../react-query";

export interface TimeSlotPayload {
  startTime: string;
  endTime: string;
}

export interface TimeSlot extends TimeSlotPayload {
  id: string;
}

export interface DayAvailability {
  date: string;
  timeSlots: TimeSlot[];
  totalHours: number;
}

export interface WorkerAvailabilityResponse {
  availability: DayAvailability[];
}

export const workerAvailabilityKey = ["worker", "availability"] as const;

export const useWorkerAvailabilityQuery = (
  options?: ApiQueryOptions<WorkerAvailabilityResponse, typeof workerAvailabilityKey>,
) => {
  return useQuery({
    queryKey: workerAvailabilityKey,
    queryFn: () =>
      apiFetch<WorkerAvailabilityResponse>({
        path: apiPaths.workerAvailability,
      }),
    ...options,
  });
};

export interface SaveWorkerAvailabilityPayload {
  date: string;
  timeSlots: TimeSlotPayload[];
}

export interface SaveWorkerAvailabilityResponse extends DayAvailability {
  updatedAt: string;
}

export const useSaveWorkerAvailabilityMutation = (
  options?: ApiMutationOptions<
    SaveWorkerAvailabilityResponse,
    SaveWorkerAvailabilityPayload
  >,
) => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ date, timeSlots }) =>
      apiFetch<SaveWorkerAvailabilityResponse>({
        path: apiPaths.workerAvailabilityForDate(date),
        method: "POST",
        body: { timeSlots },
      }),
    onSuccess: (data, variables, context, mutation) => {
      queryClient.invalidateQueries({ queryKey: workerAvailabilityKey });
      options?.onSuccess?.(data, variables, context, mutation);
    },
    ...options,
  });
};

export interface UpdateWorkerTimeSlotPayload {
  date: string;
  timeSlotId: string;
  startTime: string;
  endTime: string;
}

export interface UpdateWorkerTimeSlotResponse {
  timeSlot: TimeSlot;
  updatedAt: string;
}

export const useUpdateWorkerTimeSlotMutation = (
  options?: ApiMutationOptions<
    UpdateWorkerTimeSlotResponse,
    UpdateWorkerTimeSlotPayload
  >,
) => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ date, timeSlotId, ...payload }) =>
      apiFetch<UpdateWorkerTimeSlotResponse>({
        path: apiPaths.workerAvailabilityTimeSlot(date, timeSlotId),
        method: "PUT",
        body: payload,
      }),
    onSuccess: (data, variables, context, mutation) => {
      queryClient.invalidateQueries({ queryKey: workerAvailabilityKey });
      options?.onSuccess?.(data, variables, context, mutation);
    },
    ...options,
  });
};

export interface DeleteWorkerTimeSlotPayload {
  date: string;
  timeSlotId: string;
}

export interface DeleteWorkerTimeSlotResponse {
  message: string;
  deletedAt: string;
}

export const useDeleteWorkerTimeSlotMutation = (
  options?: ApiMutationOptions<
    DeleteWorkerTimeSlotResponse,
    DeleteWorkerTimeSlotPayload
  >,
) => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ date, timeSlotId }) =>
      apiFetch<DeleteWorkerTimeSlotResponse>({
        path: apiPaths.workerAvailabilityTimeSlot(date, timeSlotId),
        method: "DELETE",
      }),
    onSuccess: (data, variables, context, mutation) => {
      queryClient.invalidateQueries({ queryKey: workerAvailabilityKey });
      options?.onSuccess?.(data, variables, context, mutation);
    },
    ...options,
  });
};

export interface CopyWorkerAvailabilityPayload {
  sourceDate: string;
  targetDates: string[];
  overwrite: boolean;
}

export interface CopyWorkerAvailabilityResponse {
  copied: DayAvailability[];
  skipped: string[];
}

export const useCopyWorkerAvailabilityMutation = (
  options?: ApiMutationOptions<
    CopyWorkerAvailabilityResponse,
    CopyWorkerAvailabilityPayload
  >,
) => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload) =>
      apiFetch<CopyWorkerAvailabilityResponse>({
        path: apiPaths.workerAvailabilityCopy,
        method: "POST",
        body: payload,
      }),
    onSuccess: (data, variables, context, mutation) => {
      queryClient.invalidateQueries({ queryKey: workerAvailabilityKey });
      options?.onSuccess?.(data, variables, context, mutation);
    },
    ...options,
  });
};


