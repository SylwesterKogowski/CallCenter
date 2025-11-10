import type { LoginResponse } from "~/api/auth";

export type Worker = LoginResponse["worker"];

export interface LoginErrors {
  login?: string;
  password?: string;
  general?: string;
}

export interface WorkerSession {
  worker: Worker;
  token: string;
  expiresAt: string;
}


