import * as React from "react";

import type { WorkloadLevel, WorkerStats } from "~/api/manager";

import { WorkerStatsCard } from "./WorkerStatsCard";

type SortKey = "workloadLevel" | "ticketsCount" | "efficiency" | "timeSpent";

const workloadOrder: Record<WorkloadLevel, number> = {
  low: 1,
  normal: 2,
  high: 3,
  critical: 4,
};

const sortComparators: Record<SortKey, (a: WorkerStats, b: WorkerStats) => number> = {
  workloadLevel: (a, b) => workloadOrder[b.workloadLevel] - workloadOrder[a.workloadLevel],
  ticketsCount: (a, b) => b.ticketsCount - a.ticketsCount,
  efficiency: (a, b) => b.efficiency - a.efficiency,
  timeSpent: (a, b) => b.timeSpent - a.timeSpent,
};

const sortLabels: Record<SortKey, string> = {
  workloadLevel: "Poziom obciążenia",
  ticketsCount: "Liczba ticketów",
  efficiency: "Efektywność",
  timeSpent: "Czas spędzony",
};

const workloadFilterOptions: Array<{ value: "all" | WorkloadLevel; label: string }> = [
  { value: "all", label: "Wszyscy pracownicy" },
  { value: "low", label: "Niskie obciążenie" },
  { value: "normal", label: "Normalne obciążenie" },
  { value: "high", label: "Wysokie obciążenie" },
  { value: "critical", label: "Krytyczne obciążenie" },
];

export interface WorkerStatsSectionProps {
  workerStats: WorkerStats[];
  selectedDate: string;
  onWorkerClick?: (workerId: string) => void;
}

export const WorkerStatsSection: React.FC<WorkerStatsSectionProps> = ({
  workerStats,
  selectedDate,
  onWorkerClick,
}) => {
  const [searchTerm, setSearchTerm] = React.useState("");
  const [sortKey, setSortKey] = React.useState<SortKey>("workloadLevel");
  const [workloadFilter, setWorkloadFilter] = React.useState<"all" | WorkloadLevel>("all");
  const [expandedCard, setExpandedCard] = React.useState<string | null>(null);

  const filteredWorkers = React.useMemo(() => {
    const lowerSearch = searchTerm.trim().toLowerCase();
    return workerStats
      .filter((worker) => {
        if (workloadFilter !== "all" && worker.workloadLevel !== workloadFilter) {
          return false;
        }
        if (lowerSearch.length > 0) {
          return (
            worker.workerLogin.toLowerCase().includes(lowerSearch) ||
            worker.categories.some((category) => category.toLowerCase().includes(lowerSearch))
          );
        }
        return true;
      })
      .slice()
      .sort(sortComparators[sortKey]);
  }, [workerStats, workloadFilter, searchTerm, sortKey]);

  return (
    <section className="space-y-4" aria-labelledby="worker-stats-heading">
      <header className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 id="worker-stats-heading" className="text-xl font-semibold text-gray-900">
            Statystyki pracowników
          </h2>
          <p className="text-sm text-gray-500">Dane dla dnia {selectedDate}</p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <label className="flex items-center gap-2 text-sm text-gray-600">
            <span>Filtruj:</span>
            <select
              className="rounded-md border border-gray-300 px-2 py-1 text-sm"
              value={workloadFilter}
              onChange={(event) =>
                setWorkloadFilter(event.target.value as "all" | WorkloadLevel)
              }
            >
              {workloadFilterOptions.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
          </label>
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
          <label className="sr-only" htmlFor="worker-search">
            Wyszukaj pracownika
          </label>
          <input
            id="worker-search"
            type="search"
            placeholder="Szukaj po loginie lub kategorii"
            className="rounded-md border border-gray-300 px-3 py-1 text-sm"
            value={searchTerm}
            onChange={(event) => setSearchTerm(event.target.value)}
          />
        </div>
      </header>

      {filteredWorkers.length === 0 ? (
        <p className="rounded-md border border-dashed border-gray-300 bg-gray-50 px-4 py-6 text-center text-sm text-gray-600">
          Brak danych spełniających wybrane kryteria.
        </p>
      ) : (
        <div className="">
          {filteredWorkers.map((worker) => (
            <WorkerStatsCard
              key={worker.workerId}
              workerStats={worker}
              onWorkerClick={onWorkerClick}
              isExpanded={expandedCard === worker.workerId}
              onExpand={(workerId, expanded) => setExpandedCard(expanded ? workerId : null)}
            />
          ))}
        </div>
      )}
    </section>
  );
};


