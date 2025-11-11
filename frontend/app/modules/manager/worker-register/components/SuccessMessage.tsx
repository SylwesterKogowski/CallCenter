import * as React from "react";

import type { RegisteredWorker } from "~/api/auth";

export interface SuccessMessageProps {
  worker: RegisteredWorker;
  onRegisterAnother?: () => void;
}

const formatDate = (isoDate: string): string => {
  if (!isoDate) {
    return "";
  }
  try {
    return new Intl.DateTimeFormat("pl-PL", {
      dateStyle: "medium",
      timeStyle: "short",
    }).format(new Date(isoDate));
  } catch {
    return isoDate;
  }
};

export const SuccessMessage: React.FC<SuccessMessageProps> = ({
  worker,
  onRegisterAnother,
}) => {
  return (
    <section
      className="space-y-3 rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm text-emerald-800 shadow-sm dark:border-emerald-500/40 dark:bg-emerald-900/40 dark:text-emerald-100"
      role="status"
      aria-live="polite"
    >
      <header>
        <h2 className="text-base font-semibold">
          Pracownik został pomyślnie zarejestrowany.
        </h2>
        <p className="mt-1 text-xs uppercase tracking-wide text-emerald-700/80 dark:text-emerald-200/80">
          Dane konta
        </p>
      </header>

      <dl className="grid gap-2 md:grid-cols-2">
        <div>
          <dt className="text-xs text-emerald-700/70 dark:text-emerald-200/80">
            Login
          </dt>
          <dd className="text-sm font-medium">{worker.login}</dd>
        </div>
        <div>
          <dt className="text-xs text-emerald-700/70 dark:text-emerald-200/80">
            Uprawnienia
          </dt>
          <dd className="text-sm font-medium">
            {worker.isManager ? "Manager" : "Pracownik"}
          </dd>
        </div>
        <div>
          <dt className="text-xs text-emerald-700/70 dark:text-emerald-200/80">
            Data utworzenia
          </dt>
          <dd className="text-sm font-medium">{formatDate(worker.createdAt)}</dd>
        </div>
        <div>
          <dt className="text-xs text-emerald-700/70 dark:text-emerald-200/80">
            ID pracownika
          </dt>
          <dd className="text-xs font-mono">{worker.id}</dd>
        </div>
      </dl>

      {onRegisterAnother ? (
        <div>
          <button
            type="button"
            onClick={onRegisterAnother}
            className="rounded-md border border-emerald-200 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-wide text-emerald-700 transition hover:bg-emerald-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-300 dark:border-emerald-500/40 dark:bg-emerald-900 dark:text-emerald-100 dark:hover:bg-emerald-800"
          >
            Zarejestruj kolejnego pracownika
          </button>
        </div>
      ) : null}
    </section>
  );
};

SuccessMessage.displayName = "SuccessMessage";


