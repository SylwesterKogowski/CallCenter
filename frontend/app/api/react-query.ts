// Plik odpowiedzialny za współdzielone aliasy typów konfiguracji TanStack Query.

import type {
  QueryKey,
  UseMutationOptions,
  UseQueryOptions,
} from "@tanstack/react-query";

import type { ApiError } from "./http";

export type ApiQueryOptions<TData, TKey extends QueryKey> = Omit<
  UseQueryOptions<TData, ApiError, TData, TKey>,
  "queryKey" | "queryFn"
>;

export type ApiMutationOptions<TData, TVariables, TContext = unknown> = Omit<
  UseMutationOptions<TData, ApiError, TVariables, TContext>,
  "mutationFn"
>;


