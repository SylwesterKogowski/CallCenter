import * as React from "react";
import { useQueryClient } from "@tanstack/react-query";

import {
  type AutoAssignmentSettings,
  type ManagerMonitoringResponse,
  type MonitoringSummary,
  type QueueStats,
  type TriggerAutoAssignmentResponse,
  type WorkerStats,
  useManagerMonitoringQuery,
  useTriggerAutoAssignmentMutation,
  useUpdateAutoAssignmentMutation,
} from "~/api/manager";
import { ApiError } from "~/api/http";

import { AutoAssignmentSection } from "./components/AutoAssignmentSection";
import { DateSelector } from "./components/DateSelector";
import { LastUpdateIndicator } from "./components/LastUpdateIndicator";
import { ManagerMonitoringSSEConnection, type MonitoringUpdate } from "./components/SSEConnection";
import { MonitoringCharts } from "./components/MonitoringCharts";
import { QueueStatsSection } from "./components/QueueStatsSection";
import { StatisticsSummary } from "./components/StatisticsSummary";
import { WorkerStatsSection } from "./components/WorkerStatsSection";
import { getTodayDate } from "./utils";

const managerMonitoringKey = (date: string) =>
  ["manager", "monitoring", { date }] as const;

const isRecord = (value: unknown): value is Record<string, unknown> =>
  typeof value === "object" && value !== null;

const queueStatusField: Record<string, keyof QueueStats> = {
  waiting: "waitingTickets",
  in_progress: "inProgressTickets",
  completed: "completedTickets",
};

const summaryStatusField: Record<string, keyof MonitoringSummary> = {
  waiting: "waitingTicketsTotal",
  in_progress: "inProgressTicketsTotal",
  completed: "completedTicketsTotal",
};

const ensureSettings = (
  settings: AutoAssignmentSettings | null | undefined,
): AutoAssignmentSettings["settings"] => {
  if (!settings) {
    return {
      considerEfficiency: true,
      considerAvailability: true,
      maxTicketsPerWorker: 10,
    };
  }
  return settings.settings;
};

export interface ManagerMonitoringProps {
  managerId: string;
  defaultDate?: string;
  minDate?: string;
  maxDate?: string;
}

export const ManagerMonitoring: React.FC<ManagerMonitoringProps> = ({
  managerId,
  defaultDate,
  minDate,
  maxDate,
}) => {
  const today = React.useMemo(() => getTodayDate(), []);
  const resolvedDefaultDate = defaultDate ?? today;
  const resolvedMaxDate = maxDate ?? today;

  const [selectedDate, setSelectedDate] = React.useState(resolvedDefaultDate);
  const [lastUpdate, setLastUpdate] = React.useState<string | null>(null);
  const [isSseConnected, setIsSseConnected] = React.useState(false);
  const [sseError, setSseError] = React.useState<string | null>(null);
  const [autoAssignmentError, setAutoAssignmentError] = React.useState<string | null>(null);
  const [lastTriggerResult, setLastTriggerResult] =
    React.useState<TriggerAutoAssignmentResponse | null>(null);

  const queryClient = useQueryClient();
  const queryKey = React.useMemo(() => managerMonitoringKey(selectedDate), [selectedDate]);

  const monitoringQuery = useManagerMonitoringQuery(
    { date: selectedDate },
    {
      keepPreviousData: true,
      refetchOnWindowFocus: false,
      onSuccess: () => {
        setLastUpdate(new Date().toISOString());
        setSseError(null);
      },
    },
  );

  const updateAutoAssignmentMutation = useUpdateAutoAssignmentMutation();
  const triggerAutoAssignmentMutation = useTriggerAutoAssignmentMutation({
    onSuccess: (response) => {
      setLastTriggerResult(response);
    },
  });

  const handleSelectedDateChange = React.useCallback((date: string) => {
    setSelectedDate(date);
    setLastTriggerResult(null);
    setAutoAssignmentError(null);
    setLastUpdate(null);
  }, []);

  const handleRefresh = React.useCallback(() => {
    monitoringQuery.refetch();
  }, [monitoringQuery]);

  const handleUpdateAutoAssignment = React.useCallback(
    async (enabled: boolean) => {
      const current = monitoringQuery.data;
      if (!current) {
        return;
      }
      setAutoAssignmentError(null);
      try {
        await updateAutoAssignmentMutation.mutateAsync({
          enabled,
          settings: ensureSettings(current.autoAssignmentSettings),
        });
      } catch (error) {
        const message =
          error instanceof ApiError
            ? error.message
            : error instanceof Error
              ? error.message
              : "Nie udało się zaktualizować ustawień automatycznego przypisywania.";
        setAutoAssignmentError(message);
      }
    },
    [monitoringQuery.data, updateAutoAssignmentMutation],
  );

  const handleManualTrigger = React.useCallback(async () => {
    setAutoAssignmentError(null);
    try {
      await triggerAutoAssignmentMutation.mutateAsync({
        date: selectedDate,
      });
    } catch (error) {
      const message =
        error instanceof ApiError
          ? error.message
          : error instanceof Error
            ? error.message
            : "Nie udało się uruchomić automatycznego przypisywania.";
      setAutoAssignmentError(message);
    }
  }, [selectedDate, triggerAutoAssignmentMutation]);

  const applyWorkerStatsUpdate = React.useCallback(
    (
      current: ManagerMonitoringResponse,
      patch: unknown,
    ): ManagerMonitoringResponse => {
      if (!isRecord(patch) || typeof patch.workerId !== "string") {
        return current;
      }

      const workerId = patch.workerId;
      const nextWorkers = current.workerStats.some((worker) => worker.workerId === workerId)
        ? current.workerStats.map((worker) =>
            worker.workerId === workerId
              ? ({ ...worker, ...patch } as WorkerStats)
              : worker,
          )
        : [...current.workerStats, patch as WorkerStats];

      return {
        ...current,
        workerStats: nextWorkers,
      };
    },
    [],
  );

  const applyQueueStatsUpdate = React.useCallback(
    (current: ManagerMonitoringResponse, patch: unknown): ManagerMonitoringResponse => {
      if (!isRecord(patch) || typeof patch.queueId !== "string") {
        return current;
      }

      const queueId = patch.queueId;
      let queueExists = false;
      const nextQueues = current.queueStats.map((queue) => {
        if (queue.queueId === queueId) {
          queueExists = true;
          return { ...queue, ...patch } as QueueStats;
        }
        return queue;
      });

      if (!queueExists) {
        nextQueues.push({
          queueId,
          queueName: typeof patch.queueName === "string" ? patch.queueName : queueId,
          waitingTickets: Number(patch.waitingTickets) || 0,
          inProgressTickets: Number(patch.inProgressTickets) || 0,
          completedTickets: Number(patch.completedTickets) || 0,
          totalTickets: Number(patch.totalTickets) || 0,
          averageResolutionTime: Number(patch.averageResolutionTime) || 0,
          assignedWorkers: Number(patch.assignedWorkers) || 0,
        });
      }

      return {
        ...current,
        queueStats: nextQueues,
      };
    },
    [],
  );

  const applyTicketAdded = React.useCallback(
    (current: ManagerMonitoringResponse, payload: unknown): ManagerMonitoringResponse => {
      if (!isRecord(payload)) {
        return current;
      }
      const status = typeof payload.status === "string" ? payload.status : "waiting";
      const queueId = typeof payload.queueId === "string" ? payload.queueId : null;

      const nextSummary: MonitoringSummary = {
        ...current.summary,
        totalTickets: current.summary.totalTickets + 1,
      };

      const summaryKey = summaryStatusField[status];
      if (summaryKey) {
        nextSummary[summaryKey] = Math.max(0, current.summary[summaryKey]) + 1;
      }

      const nextQueues = current.queueStats.map((queue) => {
        if (!queueId || queue.queueId !== queueId) {
          return queue;
        }

        const statusKey = queueStatusField[status];
        const updatedQueue: QueueStats = {
          ...queue,
          totalTickets: queue.totalTickets + 1,
        };
        if (statusKey) {
          updatedQueue[statusKey] = Math.max(0, queue[statusKey]) + 1;
        }
        return updatedQueue;
      });

      if (queueId && !nextQueues.some((queue) => queue.queueId === queueId)) {
        nextQueues.push({
          queueId,
          queueName: queueId,
          waitingTickets: status === "waiting" ? 1 : 0,
          inProgressTickets: status === "in_progress" ? 1 : 0,
          completedTickets: status === "completed" ? 1 : 0,
          totalTickets: 1,
          averageResolutionTime: 0,
          assignedWorkers: 0,
        });
      }

      return {
        ...current,
        summary: nextSummary,
        queueStats: nextQueues,
      };
    },
    [],
  );

  const applyTicketStatusChanged = React.useCallback(
    (current: ManagerMonitoringResponse, payload: unknown): ManagerMonitoringResponse => {
      if (!isRecord(payload)) {
        return current;
      }
      const oldStatus = typeof payload.oldStatus === "string" ? payload.oldStatus : null;
      const newStatus = typeof payload.newStatus === "string" ? payload.newStatus : null;
      const queueId = typeof payload.queueId === "string" ? payload.queueId : null;
      const workerId = typeof payload.workerId === "string" ? payload.workerId : null;

      if (!oldStatus && !newStatus) {
        return current;
      }

      const nextSummary: MonitoringSummary = { ...current.summary };
      if (oldStatus && summaryStatusField[oldStatus]) {
        const key = summaryStatusField[oldStatus];
        nextSummary[key] = Math.max(0, nextSummary[key] - 1);
      }
      if (newStatus && summaryStatusField[newStatus]) {
        const key = summaryStatusField[newStatus];
        nextSummary[key] = nextSummary[key] + 1;
      }

      const nextQueues = current.queueStats.map((queue) => {
        if (!queueId || queue.queueId !== queueId) {
          return queue;
        }
        const updatedQueue: QueueStats = { ...queue };
        if (oldStatus && queueStatusField[oldStatus]) {
          const key = queueStatusField[oldStatus];
          updatedQueue[key] = Math.max(0, updatedQueue[key] - 1);
        }
        if (newStatus && queueStatusField[newStatus]) {
          const key = queueStatusField[newStatus];
          updatedQueue[key] = updatedQueue[key] + 1;
        }
        return updatedQueue;
      });

      const nextWorkers = current.workerStats.map((worker) => {
        if (!workerId || worker.workerId !== workerId) {
          return worker;
        }
        const updatedWorker: WorkerStats = { ...worker };
        if (oldStatus) {
          if (oldStatus === "waiting") {
            updatedWorker.waitingTickets = Math.max(0, updatedWorker.waitingTickets - 1);
          } else if (oldStatus === "in_progress") {
            updatedWorker.inProgressTickets = Math.max(0, updatedWorker.inProgressTickets - 1);
          } else if (oldStatus === "completed") {
            updatedWorker.completedTickets = Math.max(0, updatedWorker.completedTickets - 1);
          }
        }
        if (newStatus) {
          if (newStatus === "waiting") {
            updatedWorker.waitingTickets += 1;
          } else if (newStatus === "in_progress") {
            updatedWorker.inProgressTickets += 1;
          } else if (newStatus === "completed") {
            updatedWorker.completedTickets += 1;
            updatedWorker.ticketsCount += 1;
          }
        }
        return updatedWorker;
      });

      return {
        ...current,
        summary: nextSummary,
        queueStats: nextQueues,
        workerStats: nextWorkers,
      };
    },
    [],
  );

  const handleSseUpdate = React.useCallback(
    (update: MonitoringUpdate) => {
      setLastUpdate(update.timestamp);
      setSseError(null);

      queryClient.setQueryData(queryKey, (current?: ManagerMonitoringResponse) => {
        if (!current) {
          return current;
        }

        switch (update.type) {
          case "worker_stats_updated":
            return applyWorkerStatsUpdate(current, update.data);
          case "queue_stats_updated":
            return applyQueueStatsUpdate(current, update.data);
          case "ticket_added":
            return applyTicketAdded(current, update.data);
          case "ticket_status_changed":
            return applyTicketStatusChanged(current, update.data);
          default:
            return current;
        }
      });
    },
    [
      applyQueueStatsUpdate,
      applyTicketAdded,
      applyTicketStatusChanged,
      applyWorkerStatsUpdate,
      queryClient,
      queryKey,
    ],
  );

  const handleSseError = React.useCallback((error: Error) => {
    setSseError(error.message);
  }, []);

  const isLoading = monitoringQuery.isLoading || (!monitoringQuery.data && monitoringQuery.isFetching);
  const hasError = monitoringQuery.isError;
  const errorMessage =
    hasError && monitoringQuery.error instanceof Error
      ? monitoringQuery.error.message
      : hasError
        ? "Nie udało się pobrać danych monitoringu."
        : null;

  const monitoringData = monitoringQuery.data;

  return (
    <div className="space-y-6" data-testid="manager-monitoring">
      <DateSelector
        selectedDate={selectedDate}
        minDate={minDate}
        maxDate={resolvedMaxDate}
        onDateChange={handleSelectedDateChange}
        isDisabled={isLoading}
      />

      <LastUpdateIndicator
        lastUpdate={lastUpdate}
        isConnected={isSseConnected}
        isRefreshing={monitoringQuery.isFetching}
        onRefresh={handleRefresh}
      />

      {sseError ? (
        <div className="rounded-md border border-yellow-200 bg-yellow-50 px-4 py-3 text-sm text-yellow-800">
          {sseError}
        </div>
      ) : null}

      {errorMessage ? (
        <div className="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          {errorMessage}
        </div>
      ) : null}

      {isLoading ? (
        <div className="flex items-center justify-center rounded-lg border border-gray-200 bg-white py-12 text-gray-500">
          Ładujemy dane monitoringu...
        </div>
      ) : null}

      {monitoringData ? (
        <>
          <StatisticsSummary summary={monitoringData.summary} selectedDate={selectedDate} />

          <MonitoringCharts
            workerStats={monitoringData.workerStats}
            queueStats={monitoringData.queueStats}
            selectedDate={selectedDate}
          />

          <div className="grid gap-6 lg:grid-cols-2">
            <WorkerStatsSection
              workerStats={monitoringData.workerStats}
              selectedDate={selectedDate}
              onWorkerClick={(workerId) => {
                console.info(`[manager-monitoring] Wybrano pracownika ${workerId}`);
              }}
            />
            <QueueStatsSection
              queueStats={monitoringData.queueStats}
              selectedDate={selectedDate}
              onQueueClick={(queueId) => {
                console.info(`[manager-monitoring] Wybrano kolejkę ${queueId}`);
              }}
            />
          </div>

          <AutoAssignmentSection
            settings={monitoringData.autoAssignmentSettings}
            onToggle={handleUpdateAutoAssignment}
            onManualTrigger={handleManualTrigger}
            isUpdating={updateAutoAssignmentMutation.isPending}
            isTriggering={triggerAutoAssignmentMutation.isPending}
            lastTriggerResult={lastTriggerResult}
            error={autoAssignmentError}
          />
        </>
      ) : null}

      <ManagerMonitoringSSEConnection
        managerId={managerId}
        selectedDate={selectedDate}
        onUpdate={handleSseUpdate}
        onError={handleSseError}
        onConnectionChange={(connected) => setIsSseConnected(connected)}
      />
    </div>
  );
};

export default ManagerMonitoring;


