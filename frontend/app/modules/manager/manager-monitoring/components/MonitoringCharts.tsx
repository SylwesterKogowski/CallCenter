import * as React from "react";

import type { QueueStats, WorkerStats } from "~/api/manager";

import { clamp, formatPercentage } from "../utils";

export interface MonitoringChartsProps {
  workerStats: WorkerStats[];
  queueStats: QueueStats[];
  selectedDate: string;
  chartType?: "bar" | "line" | "pie";
}

const getMaxValue = (values: number[]) => {
  const max = Math.max(...values, 0);
  return max === 0 ? 1 : max;
};

export const MonitoringCharts: React.FC<MonitoringChartsProps> = ({
  workerStats,
  queueStats,
  selectedDate,
  chartType = "bar",
}) => {
  const workerMax = React.useMemo(
    () => getMaxValue(workerStats.map((worker) => worker.ticketsCount)),
    [workerStats],
  );
  const queueMax = React.useMemo(
    () => getMaxValue(queueStats.map((queue) => queue.waitingTickets)),
    [queueStats],
  );

  return (
    <section className="space-y-4" aria-labelledby="monitoring-charts-heading">
      <header>
        <h2 id="monitoring-charts-heading" className="text-xl font-semibold text-gray-900">
          Wizualizacja danych
        </h2>
        <p className="text-sm text-gray-500">Wykresy dla dnia {selectedDate}</p>
      </header>

      <div className="grid gap-6 lg:grid-cols-2">
        <div className="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
          <h3 className="text-lg font-semibold text-gray-800">Obciążenie pracowników</h3>
          <p className="text-xs text-gray-500">Typ wykresu: {chartType === "bar" ? "słupkowy" : chartType}</p>
          <div className="mt-4 space-y-3">
            {workerStats.length === 0 ? (
              <p className="text-sm text-gray-500">Brak danych dla pracowników.</p>
            ) : (
              workerStats.map((worker) => {
                const ratio = worker.ticketsCount / workerMax;
                const width = `${clamp(ratio, 0, 1) * 100}%`;
                return (
                  <div key={worker.workerId}>
                    <div className="flex items-center justify-between text-xs text-gray-600">
                      <span>{worker.workerLogin}</span>
                      <span>{worker.ticketsCount} ticketów</span>
                    </div>
                    <div className="mt-1 h-3 rounded-full bg-blue-100">
                      <div
                        className="h-3 rounded-full bg-blue-500"
                        style={{ width }}
                        role="presentation"
                      />
                    </div>
                  </div>
                );
              })
            )}
          </div>
        </div>

        <div className="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
          <h3 className="text-lg font-semibold text-gray-800">Obciążenie kolejek</h3>
          <p className="text-xs text-gray-500">
            Pokazuje liczbę oczekujących ticketów w poszczególnych kolejkach.
          </p>
          <div className="mt-4 space-y-3">
            {queueStats.length === 0 ? (
              <p className="text-sm text-gray-500">Brak danych dla kolejek.</p>
            ) : (
              queueStats.map((queue) => {
                const ratio = queue.waitingTickets / queueMax;
                const width = `${clamp(ratio, 0, 1) * 100}%`;
                return (
                  <div key={queue.queueId}>
                    <div className="flex items-center justify-between text-xs text-gray-600">
                      <span>{queue.queueName}</span>
                      <span>{queue.waitingTickets} oczekujące</span>
                    </div>
                    <div className="mt-1 h-3 rounded-full bg-red-100">
                      <div
                        className="h-3 rounded-full bg-red-500"
                        style={{ width }}
                        role="presentation"
                      />
                    </div>
                  </div>
                );
              })
            )}
          </div>
        </div>
      </div>

      <div className="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        <h3 className="text-lg font-semibold text-gray-800">Efektywność pracowników</h3>
        <p className="text-xs text-gray-500">
          Wskaźnik pokazuje efektywność w skali procentowej. Im ciemniejszy kolor, tym większa efektywność.
        </p>
        <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          {workerStats.length === 0 ? (
            <p className="text-sm text-gray-500 sm:col-span-2 lg:col-span-4">
              Brak danych efektywności do wyświetlenia.
            </p>
          ) : (
            workerStats.map((worker) => {
              const intensity = clamp(worker.efficiency, 0, 100) / 100;
              const backgroundColor = `rgba(59, 130, 246, ${0.3 + intensity * 0.7})`;
              return (
                <div
                  key={worker.workerId}
                  className="rounded-md border border-blue-200 px-3 py-2 text-sm text-gray-800"
                  style={{ backgroundColor }}
                >
                  <p className="font-semibold">{worker.workerLogin}</p>
                  <p className="text-xs text-gray-600">Efektywność: {formatPercentage(worker.efficiency)}</p>
                </div>
              );
            })
          )}
        </div>
      </div>
    </section>
  );
};


