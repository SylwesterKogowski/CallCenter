import * as React from "react";

import type { MonitoringSummary } from "~/api/manager";

import { formatMinutes, formatPercentage } from "../utils";

export interface StatisticsSummaryProps {
  summary: MonitoringSummary;
  selectedDate: string;
}

const summaryItems: Array<{
  id: keyof MonitoringSummary;
  label: string;
  format?: (value: number) => string;
  accentClass: string;
}> = [
  { id: "totalTickets", label: "Łącznie ticketów", accentClass: "bg-blue-50 text-blue-900" },
  { id: "totalWorkers", label: "Aktywni pracownicy", accentClass: "bg-green-50 text-green-900" },
  { id: "totalQueues", label: "Kolejki", accentClass: "bg-purple-50 text-purple-900" },
  {
    id: "averageWorkload",
    label: "Średnie obciążenie",
    accentClass: "bg-yellow-50 text-yellow-900",
    format: formatPercentage,
  },
  {
    id: "averageResolutionTime",
    label: "Średni czas rozwiązania",
    accentClass: "bg-orange-50 text-orange-900",
    format: formatMinutes,
  },
  {
    id: "waitingTicketsTotal",
    label: "Oczekujące tickety",
    accentClass: "bg-red-50 text-red-900",
  },
  {
    id: "inProgressTicketsTotal",
    label: "Tickety w toku",
    accentClass: "bg-yellow-100 text-yellow-900",
  },
  {
    id: "completedTicketsTotal",
    label: "Zamknięte tickety",
    accentClass: "bg-emerald-50 text-emerald-900",
  },
];

export const StatisticsSummary: React.FC<StatisticsSummaryProps> = ({ summary, selectedDate }) => {
  return (
    <section className="space-y-3" aria-labelledby="monitoring-summary-heading">
      <header>
        <h2 id="monitoring-summary-heading" className="text-xl font-semibold text-gray-900">
          Podsumowanie systemu
        </h2>
        <p className="text-sm text-gray-500">Stan systemu dla dnia {selectedDate}</p>
      </header>

      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        {summaryItems.map((item) => (
          <div
            key={item.id}
            className={`rounded-lg border border-gray-200 px-4 py-3 shadow-sm ${item.accentClass}`}
          >
            <p className="text-xs font-semibold uppercase text-gray-600">{item.label}</p>
            <p className="text-2xl font-bold text-gray-900">
              {item.format ? item.format(summary[item.id]) : summary[item.id]}
            </p>
          </div>
        ))}
      </div>
    </section>
  );
};


