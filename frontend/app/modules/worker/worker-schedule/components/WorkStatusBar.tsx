import * as React from "react";

import type { DayStats, WorkStatus } from "~/api/worker/schedule";

const statusStyles: Record<
  WorkStatus["level"],
  { badge: string; container: string; icon: string }
> = {
  low: {
    badge: "bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-200",
    container: "border-amber-200 bg-amber-50 dark:border-amber-500/40 dark:bg-amber-900/30",
    icon: "bg-amber-400",
  },
  normal: {
    badge: "bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200",
    container:
      "border-emerald-200 bg-emerald-50 dark:border-emerald-500/40 dark:bg-emerald-900/30",
    icon: "bg-emerald-400",
  },
  high: {
    badge: "bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-200",
    container: "border-indigo-200 bg-indigo-50 dark:border-indigo-500/40 dark:bg-indigo-900/30",
    icon: "bg-indigo-400",
  },
  critical: {
    badge: "bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-200",
    container: "border-red-200 bg-red-50 dark:border-red-500/40 dark:bg-red-900/30",
    icon: "bg-red-500",
  },
};

interface WorkStatusBarProps {
  workStatus: WorkStatus | null;
  todayStats: DayStats | null;
  isLoading: boolean;
  isError?: boolean;
}

export const WorkStatusBar: React.FC<WorkStatusBarProps> = ({
  workStatus,
  todayStats,
  isLoading,
  isError = false,
}) => {
  if (isLoading) {
    return (
      <div className="flex animate-pulse flex-col gap-2 rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-950">
        <div className="h-4 w-48 rounded bg-slate-200 dark:bg-slate-800" />
        <div className="flex gap-3">
          <div className="h-3 w-24 rounded bg-slate-200 dark:bg-slate-800" />
          <div className="h-3 w-24 rounded bg-slate-200 dark:bg-slate-800" />
          <div className="h-3 w-24 rounded bg-slate-200 dark:bg-slate-800" />
        </div>
      </div>
    );
  }

  if (isError) {
    return (
      <div className="rounded-xl border border-red-200 bg-red-50 p-5 text-sm text-red-700 shadow-sm dark:border-red-500/40 dark:bg-red-900/40 dark:text-red-200">
        Nie udało się pobrać informacji o obciążeniu pracą. Spróbuj odświeżyć stronę.
      </div>
    );
  }

  if (!workStatus) {
    return (
      <div className="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-500 shadow-sm dark:border-slate-800 dark:bg-slate-950 dark:text-slate-300">
        Brak danych o obciążeniu pracą. Zgłoś problem do zespołu technicznego.
      </div>
    );
  }

  const styles = statusStyles[workStatus.level];

  return (
    <section
      className={[
        "flex flex-col gap-4 rounded-xl border p-5 shadow-sm",
        styles.container,
      ].join(" ")}
      data-testid="worker-schedule-work-status"
    >
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-2">
          <span className={`h-2.5 w-2.5 rounded-full ${styles.icon}`} />
          <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100">
            Obciążenie pracą
          </h2>
        </div>
        <span className={`rounded-full px-3 py-1 text-xs font-medium ${styles.badge}`}>
          {workStatus.level}
        </span>
      </div>

      <p className="text-sm text-slate-700 dark:text-slate-200">{workStatus.message}</p>

      <dl className="grid gap-4 sm:grid-cols-3">
        <div>
          <dt className="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
            Liczba ticketów
          </dt>
          <dd className="text-lg font-semibold text-slate-900 dark:text-slate-100">
            {todayStats?.ticketsCount ?? workStatus.ticketsCount}
          </dd>
        </div>
        <div>
          <dt className="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
            Czas spędzony
          </dt>
          <dd className="text-lg font-semibold text-slate-900 dark:text-slate-100">
            {workStatus.timeSpent} min
          </dd>
        </div>
        <div>
          <dt className="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
            Plan na dziś
          </dt>
          <dd className="text-lg font-semibold text-slate-900 dark:text-slate-100">
            {workStatus.timePlanned} min
          </dd>
        </div>
      </dl>

      {todayStats ? (
        <div className="grid gap-2 rounded-lg border border-dashed border-white/60 bg-white/40 p-4 text-xs text-slate-700 backdrop-blur dark:border-white/10 dark:bg-slate-900/30 dark:text-slate-200 sm:grid-cols-3">
          <div className="flex items-center justify-between">
            <span>Zakończone</span>
            <span className="font-semibold">{todayStats.completedTickets}</span>
          </div>
          <div className="flex items-center justify-between">
            <span>W toku</span>
            <span className="font-semibold">{todayStats.inProgressTickets}</span>
          </div>
          <div className="flex items-center justify-between">
            <span>Oczekujące</span>
            <span className="font-semibold">{todayStats.waitingTickets}</span>
          </div>
        </div>
      ) : null}
    </section>
  );
};


