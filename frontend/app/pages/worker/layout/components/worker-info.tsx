import * as React from "react";

import type { WorkerSummary } from "../types";

interface WorkerInfoProps {
  worker: WorkerSummary;
  onLogout?: () => void;
}

export const WorkerInfo: React.FC<WorkerInfoProps> = ({ worker, onLogout }) => {
  return (
    <div className="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-2 shadow-sm dark:border-slate-800 dark:bg-slate-950">
      <div className="flex h-10 w-10 items-center justify-center rounded-full bg-blue-600 text-base font-semibold text-white">
        {worker.login.slice(0, 2).toUpperCase()}
      </div>
      <div className="flex flex-col">
        <span className="text-sm font-semibold text-slate-900 dark:text-slate-100">
          {worker.login}
        </span>
        <span className="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
          {worker.isManager ? "Manager" : "Pracownik"}
        </span>
      </div>
      {onLogout ? (
        <button
          type="button"
          onClick={onLogout}
          className="ml-2 rounded-lg border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-600 transition hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800"
        >
          Wyloguj
        </button>
      ) : null}
    </div>
  );
};


