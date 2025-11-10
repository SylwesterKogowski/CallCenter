import * as React from "react";

import type { ChatErrors } from "../types";

export interface ErrorDisplayProps {
  errors: ChatErrors;
  onDismiss?: () => void;
}

interface ErrorEntry {
  key: keyof ChatErrors;
  message: string;
}

const collectMessages = (errors: ChatErrors) => {
  const messages: ErrorEntry[] = [];

  if (errors.message) {
    messages.push({ key: "message", message: errors.message });
  }

  if (errors.connection) {
    messages.push({ key: "connection", message: errors.connection });
  }

  if (errors.api) {
    messages.push({ key: "api", message: errors.api });
  }

  if (errors.general) {
    messages.push({ key: "general", message: errors.general });
  }

  return messages;
};

export const ErrorDisplay: React.FC<ErrorDisplayProps> = ({ errors, onDismiss }) => {
  const messages = collectMessages(errors);

  if (messages.length === 0) {
    return null;
  }

  return (
    <div
      role="alert"
      aria-live="assertive"
      className="rounded-lg border border-red-300 bg-red-50 p-4 text-red-800 shadow-sm dark:border-red-600/80 dark:bg-red-950 dark:text-red-200"
      data-error-field
    >
      <div className="flex items-start justify-between gap-3">
        <div className="space-y-2">
          <h2 className="text-sm font-semibold uppercase tracking-wide">Wystąpiły problemy</h2>
          <ul className="list-disc space-y-1 pl-5 text-sm">
            {messages.map(({ key, message }) => (
              <li key={key}>{message}</li>
            ))}
          </ul>
        </div>
        {onDismiss ? (
          <button
            type="button"
            className="rounded-md border border-red-200 bg-white px-2 py-1 text-xs font-medium text-red-700 transition hover:bg-red-100 focus:outline-none focus-visible:ring focus-visible:ring-red-400 dark:border-red-700 dark:bg-red-900 dark:text-red-100 dark:hover:bg-red-800"
            onClick={onDismiss}
          >
            Zamknij
          </button>
        ) : null}
      </div>
    </div>
  );
};
