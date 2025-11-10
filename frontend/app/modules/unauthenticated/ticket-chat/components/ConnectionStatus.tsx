import * as React from "react";

import type { ChatConnectionStatus } from "../types";

export interface ConnectionStatusProps {
  status: ChatConnectionStatus;
  error?: string | null;
  onRetry?: () => void;
}

const statusLabel: Record<ChatConnectionStatus, string> = {
  connecting: "Laczenie z serwerem",
  connected: "Polaczono z serwerem",
  disconnected: "Polaczenie przerwane",
  error: "Blad polaczenia",
};

const statusDotClass: Record<ChatConnectionStatus, string> = {
  connecting: "bg-amber-400",
  connected: "bg-emerald-500",
  disconnected: "bg-slate-400",
  error: "bg-red-500",
};

export const ConnectionStatus: React.FC<ConnectionStatusProps> = ({
  status,
  error,
  onRetry,
}) => {
  return (
    <div
      role="status"
      aria-live="polite"
      className="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
    >
      <div className="flex items-center gap-2">
        <span className={`inline-flex h-2.5 w-2.5 rounded-full ${statusDotClass[status]}`} aria-hidden="true" />
        <span className="font-medium">{statusLabel[status]}</span>
      </div>
      {onRetry && (status === "error" || status === "disconnected") ? (
        <button
          type="button"
          className="rounded-md border border-slate-300 bg-white px-2 py-1 text-xs font-medium text-slate-700 transition hover:bg-slate-100 focus:outline-none focus-visible:ring focus-visible:ring-slate-400 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
          onClick={onRetry}
          aria-label="Ponow polaczenie"
        >
          Ponow polaczenie
        </button>
      ) : null}
      {error ? (
        <p className="sr-only" role="alert">
          {error}
        </p>
      ) : null}
    </div>
  );
};
