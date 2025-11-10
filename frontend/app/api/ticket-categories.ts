// Plik odpowiedzialny za hook pobierający listę kategorii/ kolejek ticketów.

import { useQuery } from "@tanstack/react-query";

import { apiFetch, apiPaths } from "./http";
import type { ApiQueryOptions } from "./react-query";
import type { TicketCategory } from "./types";

export interface TicketCategoriesResponse {
  categories: TicketCategory[];
}

export const ticketCategoriesKey = ["ticketCategories"] as const;

export const useTicketCategoriesQuery = (
  options?: ApiQueryOptions<
    TicketCategoriesResponse,
    typeof ticketCategoriesKey
  >,
) => {
  return useQuery({
    queryKey: ticketCategoriesKey,
    queryFn: () =>
      apiFetch<TicketCategoriesResponse>({
        path: apiPaths.ticketCategories,
      }),
    ...options,
  });
};


