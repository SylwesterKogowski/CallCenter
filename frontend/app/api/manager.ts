// Plik odpowiedzialny za hooki TanStack Query obsługujące panel monitoringu menedżera.
// Powiązany kontroler backendu: [ManagerMonitoringController](../../../backend/src/Modules/BackendForFrontend/Manager/ManagerMonitoringController.php)

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { apiFetch, apiPaths, type QueryParams } from "./http";
import type { ApiMutationOptions, ApiQueryOptions } from "./react-query";

export interface MonitoringSummary {
  totalTickets: number;
  totalWorkers: number;
  totalQueues: number;
  averageWorkload: number;
  averageResolutionTime: number;
  waitingTicketsTotal: number;
  inProgressTicketsTotal: number;
  completedTicketsTotal: number;
}

export type WorkloadLevel = "low" | "normal" | "high" | "critical";

export interface WorkerStats {
  workerId: string;
  workerLogin: string;
  ticketsCount: number;
  timeSpent: number;
  timePlanned: number;
  workloadLevel: WorkloadLevel;
  efficiency: number;
  categories: string[];
  completedTickets: number;
  inProgressTickets: number;
  waitingTickets: number;
}

export interface QueueStats {
  queueId: string;
  queueName: string;
  waitingTickets: number;
  inProgressTickets: number;
  completedTickets: number;
  totalTickets: number;
  averageResolutionTime: number;
  assignedWorkers: number;
}

export interface AutoAssignmentSettings {
  enabled: boolean;
  lastRun: string | null;
  ticketsAssigned: number;
  settings: {
    considerEfficiency: boolean;
    considerAvailability: boolean;
    maxTicketsPerWorker: number;
  };
}

export interface ManagerMonitoringResponse {
  date: string;
  summary: MonitoringSummary;
  workerStats: WorkerStats[];
  queueStats: QueueStats[];
  autoAssignmentSettings: AutoAssignmentSettings;
}

export interface ManagerMonitoringQueryParams {
  date: string;
}

const managerMonitoringKey = (params: ManagerMonitoringQueryParams) =>
  ["manager", "monitoring", params] as const;

export const useManagerMonitoringQuery = (
  params: ManagerMonitoringQueryParams,
  options?: ApiQueryOptions<
    ManagerMonitoringResponse,
    ReturnType<typeof managerMonitoringKey>
  >,
) => {
  return useQuery({
    queryKey: managerMonitoringKey(params),
    queryFn: () =>
      apiFetch<ManagerMonitoringResponse>({
        path: apiPaths.managerMonitoring,
        params: { date: params.date } satisfies QueryParams,
      }),
    ...options,
  });
};

export interface UpdateAutoAssignmentPayload {
  enabled: boolean;
  settings: {
    considerEfficiency: boolean;
    considerAvailability: boolean;
    maxTicketsPerWorker: number;
  };
}

export interface UpdateAutoAssignmentResponse {
  autoAssignmentSettings: AutoAssignmentSettings;
  updatedAt: string;
}

export interface TriggerAutoAssignmentPayload {
  date: string;
}

export interface TriggerAutoAssignmentResponse {
  message: string;
  ticketsAssigned: number;
  assignedTo: Array<{ workerId: string; ticketsCount: number }>;
  completedAt: string;
}

const managerMonitoringRootKey = ["manager", "monitoring"] as const;

export const useUpdateAutoAssignmentMutation = (
  options?: ApiMutationOptions<
    UpdateAutoAssignmentResponse,
    UpdateAutoAssignmentPayload
  >,
) => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload) =>
      apiFetch<UpdateAutoAssignmentResponse>({
        path: apiPaths.managerAutoAssignment,
        method: "PUT",
        body: payload,
      }),
    onSuccess: (data, variables, context, mutation) => {
      queryClient.invalidateQueries({ queryKey: managerMonitoringRootKey });
      options?.onSuccess?.(data, variables, context, mutation);
    },
    ...options,
  });
};

export const useTriggerAutoAssignmentMutation = (
  options?: ApiMutationOptions<
    TriggerAutoAssignmentResponse,
    TriggerAutoAssignmentPayload
  >,
) => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload) =>
      apiFetch<TriggerAutoAssignmentResponse>({
        path: apiPaths.managerAutoAssignmentTrigger,
        method: "POST",
        body: payload,
      }),
    onSuccess: (data, variables, context, mutation) => {
      queryClient.invalidateQueries({ queryKey: managerMonitoringRootKey });
      options?.onSuccess?.(data, variables, context, mutation);
    },
    ...options,
  });
};


