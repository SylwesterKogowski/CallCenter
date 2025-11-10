import type { WorkerSession } from "./types";

export const WORKER_SESSION_STORAGE_KEY = "workerSession";

const isBrowser = (): boolean => typeof window !== "undefined";

export const saveWorkerSession = (session: WorkerSession): void => {
  if (!isBrowser()) {
    return;
  }

  try {
    window.localStorage.setItem(
      WORKER_SESSION_STORAGE_KEY,
      JSON.stringify(session),
    );
  } catch (error) {
    console.warn("[worker-login] Nie udało się zapisać sesji pracownika.", error);
  }
};

export const loadWorkerSession = (): WorkerSession | null => {
  if (!isBrowser()) {
    return null;
  }

  try {
    const data = window.localStorage.getItem(WORKER_SESSION_STORAGE_KEY);

    if (!data) {
      return null;
    }

    return JSON.parse(data) as WorkerSession;
  } catch (error) {
    console.warn("[worker-login] Nie udało się odczytać sesji pracownika.", error);
    return null;
  }
};

export const clearWorkerSession = (): void => {
  if (!isBrowser()) {
    return;
  }

  try {
    window.localStorage.removeItem(WORKER_SESSION_STORAGE_KEY);
  } catch (error) {
    console.warn(
      "[worker-login] Nie udało się wyczyścić sesji pracownika.",
      error,
    );
  }
};


