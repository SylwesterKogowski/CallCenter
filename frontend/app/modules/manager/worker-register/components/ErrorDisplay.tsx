import * as React from "react";

import type { FormErrors } from "../WorkerRegisterForm";

export interface ErrorDisplayProps {
  errors: FormErrors;
  apiError: string | null;
  onDismiss?: () => void;
}

type ErrorMessage = {
  id: string;
  text: string;
};

export const ErrorDisplay: React.FC<ErrorDisplayProps> = ({ errors, apiError, onDismiss }) => {
  const messages = React.useMemo<ErrorMessage[]>(() => {
    const items: ErrorMessage[] = [];
    if (apiError) {
      items.push({ id: "api-error", text: apiError });
    }
    if (errors.general) {
      items.push({ id: "general-error", text: errors.general });
    }
    return items;
  }, [apiError, errors.general]);

  if (messages.length === 0) {
    return null;
  }

  return (
    <div
      className="flex items-start gap-3 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-500/40 dark:bg-red-950/40 dark:text-red-200"
      role="alert"
    >
      <span className="mt-0.5 inline-flex h-2 w-2 flex-none rounded-full bg-red-500" />
      <div className="flex-1 space-y-1">
        {messages.map((message) => (
          <p key={message.id}>{message.text}</p>
        ))}
      </div>
      {onDismiss ? (
        <button
          type="button"
          onClick={onDismiss}
          className="flex-none rounded-md border border-transparent px-2 py-1 text-xs font-medium text-red-600 transition hover:bg-red-100 dark:text-red-200 dark:hover:bg-red-900/40"
          aria-label="Zamknij komunikat błędu"
        >
          Zamknij
        </button>
      ) : null}
    </div>
  );
};

ErrorDisplay.displayName = "ErrorDisplay";


