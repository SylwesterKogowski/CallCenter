import * as React from "react";

import type { QueueStats } from "~/api/manager";

import { QueueStatsCard } from "./QueueStatsCard";

type SortKey = "waitingTickets" | "inProgressTickets" | "completedTickets" | "averageResolutionTime";

const sortLabels: Record<SortKey, string> = {
  waitingTickets: "Oczekujące tickety",
  inProgressTickets: "Tickety w toku",
  completedTickets: "Zamknięte tickety",
  averageResolutionTime: "Średni czas rozwiązania",
};

const sortComparators: Record<SortKey, (a: QueueStats, b: QueueStats) => number> = {
  waitingTickets: (a, b) => b.waitingTickets - a.waitingTickets,
  inProgressTickets: (a, b) => b.inProgressTickets - a.inProgressTickets,
  completedTickets: (a, b) => b.completedTickets - a.completedTickets,
  averageResolutionTime: (a, b) => a.averageResolutionTime - b.averageResolutionTime,
};

export interface QueueStatsSectionProps {
  queueStats: QueueStats[];
  selectedDate: string;
  onQueueClick?: (queueId: string) => void;
}

export const QueueStatsSection: React.FC<QueueStatsSectionProps> = ({
  queueStats,
  selectedDate,
  onQueueClick,
}) => {
  const [searchTerm, setSearchTerm] = React.useState("");
  const [sortKey, setSortKey] = React.useState<SortKey>("waitingTickets");
  const [expandedQueue, setExpandedQueue] = React.useState<string | null>(null);

  const filteredQueues = React.useMemo(() => {
    const lowerSearch = searchTerm.trim().toLowerCase();
    return queueStats
      .filter((queue) =>
        lowerSearch.length > 0 ? queue.queueName.toLowerCase().includes(lowerSearch) : true,
      )
      .slice()
      .sort(sortComparators[sortKey]);
  }, [queueStats, searchTerm, sortKey]);

  return (
    <section className="space-y-4" aria-labelledby="queue-stats-heading">
      <header className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 id="queue-stats-heading" className="text-xl font-semibold text-gray-900">
            Statystyki kolejek
          </h2>
          <p className="text-sm text-gray-500">Dane dla dnia {selectedDate}</p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <label className="flex items-center gap-2 text-sm text-gray-600">
            <span>Sortuj wg:</span>
            <select
              className="rounded-md border border-gray-300 px-2 py-1 text-sm"
              value={sortKey}
              onChange={(event) => setSortKey(event.target.value as SortKey)}
            >
              {Object.entries(sortLabels).map(([key, label]) => (
                <option key={key} value={key}>
                  {label}
                </option>
              ))}
            </select>
          </label>
          <label className="sr-only" htmlFor="queue-search">
            Wyszukaj kolejkę
          </label>
          <input
            id="queue-search"
            type="search"
            placeholder="Szukaj po nazwie kolejki"
            className="rounded-md border border-gray-300 px-3 py-1 text-sm"
            value={searchTerm}
            onChange={(event) => setSearchTerm(event.target.value)}
          />
        </div>
      </header>

      {filteredQueues.length === 0 ? (
        <p className="rounded-md border border-dashed border-gray-300 bg-gray-50 px-4 py-6 text-center text-sm text-gray-600">
          Brak statystyk kolejek do wyświetlenia.
        </p>
      ) : (
        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          {filteredQueues.map((queue) => (
            <QueueStatsCard
              key={queue.queueId}
              queueStats={queue}
              onQueueClick={onQueueClick}
              isExpanded={expandedQueue === queue.queueId}
              onExpand={(queueId, expanded) => setExpandedQueue(expanded ? queueId : null)}
            />
          ))}
        </div>
      )}
    </section>
  );
};


