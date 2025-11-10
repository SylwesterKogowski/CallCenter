// Plik odpowiedzialny za hooki mutacji autoryzacyjnych (rejestracja i logowanie pracowników).

import { useMutation } from "@tanstack/react-query";

import { apiFetch, apiPaths } from "./http";
import type { ApiMutationOptions } from "./react-query";

export interface RegisterWorkerPayload {
  login: string;
  password: string;
  categoryIds: string[];
  isManager: boolean;
}

export interface RegisteredWorker {
  id: string;
  login: string;
  isManager: boolean;
  createdAt: string;
}

export interface RegisterWorkerResponse {
  worker: RegisteredWorker;
  categories: Array<{
    id: string;
    name: string;
  }>;
}

export const useRegisterWorkerMutation = (
  options?: ApiMutationOptions<RegisterWorkerResponse, RegisterWorkerPayload>,
) => {
  return useMutation({
    mutationFn: (payload) =>
      apiFetch<RegisterWorkerResponse>({
        path: apiPaths.authRegister,
        method: "POST",
        body: payload,
      }),
    ...options,
  });
};

export interface LoginPayload {
  login: string;
  password: string;
}

export interface LoginResponse {
  worker: {
    id: string;
    login: string;
    createdAt: string;
  };
  session: {
    token: string;
    expiresAt: string;
  };
}

export const useLoginMutation = (
  options?: ApiMutationOptions<LoginResponse, LoginPayload>,
) => {
  return useMutation({
    mutationFn: (payload) =>
      apiFetch<LoginResponse>({
        path: apiPaths.authLogin,
        method: "POST",
        body: payload,
      }),
    ...options,
  });
};


