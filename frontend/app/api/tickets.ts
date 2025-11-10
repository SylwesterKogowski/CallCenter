// Plik odpowiedzialny za hooki do obsługi ticketów klientów (tworzenie, szczegóły, wiadomości).

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { apiFetch, apiPaths } from "./http";
import type { ApiMutationOptions, ApiQueryOptions } from "./react-query";
import type { Client, TicketCategory, TicketStatus } from "./types";

export interface CreateTicketPayload {
  client: {
    email?: string;
    phone?: string;
    firstName?: string;
    lastName?: string;
  };
  categoryId: string;
  title?: string;
  description?: string;
}

export interface CreatedTicket {
  id: string;
  clientId: string;
  categoryId: string;
  title?: string;
  description?: string;
  status: TicketStatus;
  createdAt: string;
}

export interface CreateTicketResponse {
  ticket: CreatedTicket;
}

export const useCreateTicketMutation = (
  options?: ApiMutationOptions<CreateTicketResponse, CreateTicketPayload>,
) => {
  return useMutation({
    mutationFn: (payload) =>
      apiFetch<CreateTicketResponse>({
        path: apiPaths.tickets,
        method: "POST",
        body: payload,
      }),
    ...options,
  });
};

export interface TicketMessage {
  id: string;
  ticketId: string;
  senderType: "client" | "worker";
  senderId?: string;
  senderName?: string;
  content: string;
  createdAt: string;
  status?: "sent" | "delivered" | "read";
}

export interface TicketDetails {
  id: string;
  clientId: string;
  categoryId: string;
  categoryName: string;
  title?: string;
  description?: string;
  status: TicketStatus;
  createdAt: string;
  updatedAt: string;
}

export interface TicketDetailsResponse {
  ticket: TicketDetails;
  messages: TicketMessage[];
}

const ticketDetailsKey = (ticketId: string) =>
  ["tickets", "details", ticketId] as const;

export const useTicketDetailsQuery = (
  ticketId: string,
  options?: ApiQueryOptions<TicketDetailsResponse, ReturnType<typeof ticketDetailsKey>>,
) => {
  return useQuery({
    queryKey: ticketDetailsKey(ticketId),
    queryFn: () =>
      apiFetch<TicketDetailsResponse>({
        path: apiPaths.ticketDetails(ticketId),
      }),
    enabled: Boolean(ticketId),
    ...options,
  });
};

export interface SendTicketMessagePayload {
  ticketId: string;
  content: string;
}

export interface SendTicketMessageResponse {
  message: TicketMessage;
}

export const useSendTicketMessageMutation = (
  options?: ApiMutationOptions<
    SendTicketMessageResponse,
    SendTicketMessagePayload
  >,
) => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ ticketId, content }) =>
      apiFetch<SendTicketMessageResponse>({
        path: apiPaths.ticketMessages(ticketId),
        method: "POST",
        body: { content },
      }),
    onSuccess: (data, variables, context, mutation) => {
      queryClient.invalidateQueries({
        queryKey: ticketDetailsKey(variables.ticketId),
      });
      options?.onSuccess?.(data, variables, context, mutation);
    },
    ...options,
  });
};

export interface TicketWithContext extends TicketDetails {
  client?: Client;
  category?: TicketCategory;
}


