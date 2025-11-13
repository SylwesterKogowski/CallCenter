import * as React from "react";

import type { WorkerStats } from "~/api/manager";

import { formatMinutes, formatPercentage } from "../utils";
import { WorkloadIndicator } from "./WorkloadIndicator";

export interface WorkerStatsCardProps {
  workerStats: WorkerStats;
  onWorkerClick?: (workerId: string) => void;
  onExpand?: (workerId: string, expanded: boolean) => void;
  isExpanded?: boolean;
}

export const WorkerStatsCard: React.FC<WorkerStatsCardProps> = ({
  workerStats,
  onWorkerClick,
  onExpand,
  isExpanded,
}) => {
  const [localExpanded, setLocalExpanded] = React.useState(false);
  const isControlled = typeof isExpanded === "boolean";
  const expanded = isControlled ? (isExpanded as boolean) : localExpanded;

  const toggleExpanded = () => {
    const next = !expanded;
    if (!isControlled) {
      setLocalExpanded(next);
    }
    onExpand?.(workerStats.workerId, next);
  };

  return (
    <article
      className="flex w-full flex-col space-y-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm"
      data-testid={`worker-card-${workerStats.workerId}`}
    >
      <header className="flex items-start justify-between gap-2">
        <div className="min-w-0 flex-1">
          <h3 className="text-lg font-semibold text-gray-900">{workerStats.workerLogin}</h3>
          <p className="text-sm text-gray-500">
            Kategorie:{" "}
            {workerStats.categories.length > 0 ? workerStats.categories.join(", ") : "Brak przypisanych kategorii"}
          </p>
        </div>
        <button
          type="button"
          className="shrink-0 rounded-md border border-blue-500 px-3 py-1 text-sm font-medium text-blue-600 hover:bg-blue-50"
          onClick={() => onWorkerClick?.(workerStats.workerId)}
        >
          Szczegóły
        </button>
      </header>

      <section className="grid grid-cols-2 gap-2">
        <div className="rounded-md bg-blue-50 px-2 py-2 sm:px-3">
          <p className="text-xs font-semibold text-blue-700 uppercase">Ticketów ogółem</p>
          <p className="text-lg font-bold text-blue-900">{workerStats.ticketsCount}</p>
        </div>
        <div className="rounded-md bg-purple-50 px-2 py-2 sm:px-3">
          <p className="text-xs font-semibold text-purple-700 uppercase">Efektywność</p>
          <p className="text-lg font-bold text-purple-900">{formatPercentage(workerStats.efficiency)}</p>
        </div>
      </section>

      <WorkloadIndicator
        workloadLevel={workerStats.workloadLevel}
        timeSpent={workerStats.timeSpent}
        timePlanned={workerStats.timePlanned}
      />

      <button
        type="button"
        onClick={toggleExpanded}
        className="self-start text-sm font-medium text-blue-600 hover:underline"
        aria-expanded={expanded}
      >
        {expanded ? "Ukryj szczegóły" : "Pokaż szczegóły"}
      </button>

      {expanded ? (
        <dl className="grid gap-3 rounded-md border border-gray-100 bg-gray-50 p-3 text-sm text-gray-700 sm:grid-cols-2">
          <div>
            <dt className="font-semibold text-gray-600">Zakończone tickety</dt>
            <dd>{workerStats.completedTickets}</dd>
          </div>
          <div>
            <dt className="font-semibold text-gray-600">Tickety w toku</dt>
            <dd>{workerStats.inProgressTickets}</dd>
          </div>
          <div>
            <dt className="font-semibold text-gray-600">Tickety oczekujące</dt>
            <dd>{workerStats.waitingTickets}</dd>
          </div>
          <div>
            <dt className="font-semibold text-gray-600">Czas spędzony</dt>
            <dd>{formatMinutes(workerStats.timeSpent)}</dd>
          </div>
          <div>
            <dt className="font-semibold text-gray-600">Czas zaplanowany</dt>
            <dd>{formatMinutes(workerStats.timePlanned)}</dd>
          </div>
        </dl>
      ) : null}
    </article>
  );
};


