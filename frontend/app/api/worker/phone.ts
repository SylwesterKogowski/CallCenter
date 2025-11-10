// Plik odpowiedzialny za hooki obsługujące przepływ „odbieram telefon” po stronie pracownika.

import { useMutation, useQuery } from "@tanstack/react-query";

import { apiFetch, apiPaths } from "../http";
import type { ApiMutationOptions, ApiQueryOptions } from "../react-query";
import type { Client, TicketCategory, TicketNote, TicketStatus } from "../types";

export interface StartPhoneCallPayload {
  workerId: string;
}

export interface StartPhoneCallResponse {
  callId: string;
  startTime: string;
  pausedTickets: Array<{
    ticketId: string;
    previousStatus: TicketStatus;
    newStatus: TicketStatus;
  }>;
}

export const useStartPhoneCallMutation = (
  options?: ApiMutationOptions<StartPhoneCallResponse, StartPhoneCallPayload>,
) => {
  return useMutation({
    mutationFn: (payload) =>
      apiFetch<StartPhoneCallResponse>({
        path: apiPaths.workerPhoneReceive,
        method: "POST",
        body: payload,
      }),
    ...options,
  });
};

export interface WorkerTicketSearchParams {
  query?: string;
  categoryId?: string;
  status?: TicketStatus;
  limit?: number;
}

export interface WorkerTicketSearchResult {
  id: string;
  title: string;
  category: TicketCategory;
  status: TicketStatus;
  client: Client;
  createdAt: string;
  timeSpent: number;
}

export interface WorkerTicketSearchResponse {
  tickets: WorkerTicketSearchResult[];
  total: number;
}

const workerTicketSearchKey = (params: WorkerTicketSearchParams) =>
  ["worker", "phone", "ticketSearch", params] as const;

export const useWorkerTicketSearchQuery = (
  params: WorkerTicketSearchParams,
  options?: ApiQueryOptions<
    WorkerTicketSearchResponse,
    ReturnType<typeof workerTicketSearchKey>
  >,
) => {
  return useQuery({
    queryKey: workerTicketSearchKey(params),
    queryFn: () =>
      apiFetch<WorkerTicketSearchResponse>({
        path: apiPaths.workerTicketsSearch,
        params: {
          query: params.query,
          categoryId: params.categoryId,
          status: params.status,
          limit: params.limit,
        },
      }),
    ...options,
  });
};

export interface CreateWorkerTicketPayload {
  title: string;
  categoryId: string;
  clientId?: string | null;
  clientData?: {
    name: string;
    email?: string;
    phone?: string;
  } | null;
}

export interface WorkerTicket {
  id: string;
  title: string;
  category: TicketCategory;
  status: TicketStatus;
  client: Client;
  createdAt: string;
  timeSpent: number;
}

export interface CreateWorkerTicketResponse {
  ticket: WorkerTicket;
}

export const useCreateWorkerTicketMutation = (
  options?: ApiMutationOptions<
    CreateWorkerTicketResponse,
    CreateWorkerTicketPayload
  >,
) => {
  return useMutation({
    mutationFn: (payload) =>
      apiFetch<CreateWorkerTicketResponse>({
        path: apiPaths.workerTickets,
        method: "POST",
        body: payload,
      }),
    ...options,
  });
};

export interface AddWorkerTicketNotePayload {
  ticketId: string;
  content: string;
}

export interface AddWorkerTicketNoteResponse {
  note: TicketNote;
}

export const useAddWorkerTicketNoteMutation = (
  options?: ApiMutationOptions<
    AddWorkerTicketNoteResponse,
    AddWorkerTicketNotePayload
  >,
) => {
  return useMutation({
    mutationFn: ({ ticketId, content }) =>
      apiFetch<AddWorkerTicketNoteResponse>({
        path: apiPaths.workerTicketNotes(ticketId),
        method: "POST",
        body: { content },
      }),
    ...options,
  });
};

export interface EndPhoneCallPayload {
  callId: string;
  ticketId: string | null;
  duration: number;
  notes: string;
  startTime: string;
  endTime: string;
}

export interface EndPhoneCallResponse {
  call: {
    id: string;
    ticketId: string | null;
    duration: number;
    startTime: string;
    endTime: string;
  };
  ticket?: {
    id: string;
    status: TicketStatus;
    timeSpent: number;
    scheduledDate?: string;
    updatedAt: string;
  };
  previousTicket?: {
    id: string;
    status: TicketStatus;
    updatedAt: string;
  };
}

export const useEndPhoneCallMutation = (
  options?: ApiMutationOptions<EndPhoneCallResponse, EndPhoneCallPayload>,
) => {
  return useMutation({
    mutationFn: (payload) =>
      apiFetch<EndPhoneCallResponse>({
        path: apiPaths.workerPhoneEnd,
        method: "POST",
        body: payload,
      }),
    ...options,
  });
};

export interface WorkerClientSearchParams {
  query?: string;
  limit?: number;
}

export interface WorkerClientSearchResponse {
  clients: Client[];
  total: number;
}

const workerClientSearchKey = (params: WorkerClientSearchParams) =>
  ["worker", "phone", "clientSearch", params] as const;

export const useWorkerClientSearchQuery = (
  params: WorkerClientSearchParams,
  options?: ApiQueryOptions<
    WorkerClientSearchResponse,
    ReturnType<typeof workerClientSearchKey>
  >,
) => {
  return useQuery({
    queryKey: workerClientSearchKey(params),
    queryFn: () =>
      apiFetch<WorkerClientSearchResponse>({
        path: apiPaths.workerClientsSearch,
        params: {
          query: params.query,
          limit: params.limit,
        },
      }),
    ...options,
  });
};


