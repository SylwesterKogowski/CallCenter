import * as React from "react";

import type { LoginErrors } from "../types";

export interface ErrorDisplayProps {
  errors: LoginErrors;
  apiError?: string | null;
  onDismiss?: () => void;
}

const collectMessages = (
  errors: LoginErrors,
  apiError?: string | null,
): string[] => {
  const messages: string[] = [];

  if (errors.general) {
    messages.push(errors.general);
  }

  if (apiError) {
    messages.push(apiError);
  }

  return messages;
};

export const ErrorDisplay: React.FC<ErrorDisplayProps> = ({
  errors,
  apiError,
  onDismiss,
}) => {
  const messages = collectMessages(errors, apiError);

  if (messages.length === 0) {
    return null;
  }

  return (
    <div
      className="mb-4 flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200"
      role="alert"
      data-testid="worker-login-error-display"
    >
      <div className="flex-1 space-y-1">
        {messages.map((message) => (
          <p key={message}>{message}</p>
        ))}
      </div>
      {onDismiss ? (
        <button
          type="button"
          onClick={onDismiss}
          className="rounded-md px-2 py-1 text-xs font-medium uppercase tracking-wide text-red-700 transition hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-200 dark:text-red-200 dark:hover:bg-red-900/50 dark:focus:ring-red-700"
          aria-label="Zamknij komunikat"
        >
          Zamknij
        </button>
      ) : null}
    </div>
  );
};


