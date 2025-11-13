import * as React from "react";

import type { QueueStats } from "~/api/manager";

import { formatMinutes } from "../utils";

export interface QueueStatsCardProps {
  queueStats: QueueStats;
  onExpand?: (queueId: string, expanded: boolean) => void;
  isExpanded?: boolean;
  onQueueClick?: (queueId: string) => void;
}

export const QueueStatsCard: React.FC<QueueStatsCardProps> = ({
  queueStats,
  onExpand,
  isExpanded,
  onQueueClick,
}) => {
  const [internalExpanded, setInternalExpanded] = React.useState(false);
  const isControlled = typeof isExpanded === "boolean";
  const expanded = isControlled ? (isExpanded as boolean) : internalExpanded;

  const toggleExpanded = () => {
    const next = !expanded;
    if (!isControlled) {
      setInternalExpanded(next);
    }
    onExpand?.(queueStats.queueId, next);
  };

  return (
    <article
      className="flex w-full flex-col space-y-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm"
      data-testid={`queue-card-${queueStats.queueId}`}
    >
      <header className="flex items-start justify-between gap-2">
        <div className="min-w-0 flex-1">
          <h3 className="text-lg font-semibold text-gray-900">{queueStats.queueName}</h3>
          <p className="text-sm text-gray-500">
            Przypisanych pracowników: <span className="font-medium text-gray-700">{queueStats.assignedWorkers}</span>
          </p>
        </div>
        <button
          type="button"
          className="shrink-0 rounded-md border border-blue-500 px-3 py-1 text-sm font-medium text-blue-600 hover:bg-blue-50"
          onClick={() => onQueueClick?.(queueStats.queueId)}
        >
          Szczegóły
        </button>
      </header>

      <section className="grid grid-cols-3 gap-2">
        <div className="rounded-md bg-red-50 px-2 py-2 text-center sm:px-3">
          <p className="text-xs font-semibold uppercase text-red-700">Oczekujące</p>
          <p className="text-lg font-bold text-red-900">{queueStats.waitingTickets}</p>
        </div>
        <div className="rounded-md bg-yellow-50 px-2 py-2 text-center sm:px-3">
          <p className="text-xs font-semibold uppercase text-yellow-700">W toku</p>
          <p className="text-lg font-bold text-yellow-900">{queueStats.inProgressTickets}</p>
        </div>
        <div className="rounded-md bg-green-50 px-2 py-2 text-center sm:px-3">
          <p className="text-xs font-semibold uppercase text-green-700">Zamknięte</p>
          <p className="text-lg font-bold text-green-900">{queueStats.completedTickets}</p>
        </div>
      </section>

      <button
        type="button"
        className="self-start text-sm font-medium text-blue-600 hover:underline"
        onClick={toggleExpanded}
        aria-expanded={expanded}
      >
        {expanded ? "Ukryj szczegóły" : "Pokaż szczegóły"}
      </button>

      {expanded ? (
        <dl className="grid gap-3 rounded-md border border-gray-100 bg-gray-50 p-3 text-sm text-gray-700 sm:grid-cols-2">
          <div>
            <dt className="font-semibold text-gray-600">Łącznie ticketów</dt>
            <dd>{queueStats.totalTickets}</dd>
          </div>
          <div>
            <dt className="font-semibold text-gray-600">Średni czas rozwiązania</dt>
            <dd>{formatMinutes(queueStats.averageResolutionTime)}</dd>
          </div>
        </dl>
      ) : null}
    </article>
  );
};


