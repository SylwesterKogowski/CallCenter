import * as React from "react";

export interface TypingIndicatorProps {
  isTyping: boolean;
  workerName?: string;
}

export const TypingIndicator: React.FC<TypingIndicatorProps> = ({ isTyping, workerName }) => {
  if (!isTyping) {
    return null;
  }

  return (
    <div
      role="status"
      aria-live="polite"
      className="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-sm font-medium text-emerald-700 dark:bg-emerald-900 dark:text-emerald-200"
    >
      <span className="inline-flex h-2 w-2 animate-pulse rounded-full bg-emerald-500" aria-hidden="true" />
      <span>{workerName ? `${workerName} pisze...` : "Pracownik pisze..."}</span>
    </div>
  );
};
