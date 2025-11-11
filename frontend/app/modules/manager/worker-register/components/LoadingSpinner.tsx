import * as React from "react";

export interface LoadingSpinnerProps {
  message?: string;
}

export const LoadingSpinner: React.FC<LoadingSpinnerProps> = ({ message }) => {
  return (
    <div
      className="flex items-center justify-center gap-3 rounded-md border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
      role="status"
      aria-live="polite"
    >
      <span className="inline-flex h-4 w-4 animate-spin rounded-full border-2 border-blue-500 border-t-transparent" />
      <span>{message ?? "Przetwarzamy żądanie..."}</span>
    </div>
  );
};

LoadingSpinner.displayName = "LoadingSpinner";


