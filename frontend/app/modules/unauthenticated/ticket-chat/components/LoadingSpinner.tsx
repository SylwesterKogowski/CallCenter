import * as React from "react";

export interface LoadingSpinnerProps {
  message?: string;
}

export const LoadingSpinner: React.FC<LoadingSpinnerProps> = ({ message }) => {
  return (
    <div
      role="status"
      aria-live="polite"
      aria-busy="true"
      className="flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4 text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
    >
      <span className="inline-flex h-4 w-4 animate-spin rounded-full border-2 border-slate-400 border-t-transparent" />
      <span>{message ?? "Wczytywanie danych..."}</span>
    </div>
  );
};
