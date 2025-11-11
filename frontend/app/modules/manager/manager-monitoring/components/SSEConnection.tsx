import * as React from "react";

import { subscribeToMercure, type MercurePayload } from "~/api/SSE/mercureClient";

export type MonitoringEventType =
  | "worker_stats_updated"
  | "queue_stats_updated"
  | "ticket_added"
  | "ticket_status_changed";

export interface MonitoringUpdate<TData = unknown> {
  type: MonitoringEventType;
  data: TData;
  timestamp: string;
}

export interface ManagerMonitoringSSEConnectionProps {
  managerId: string;
  selectedDate: string;
  onUpdate: (update: MonitoringUpdate) => void;
  onError?: (error: Error) => void;
  onConnectionChange?: (isConnected: boolean) => void;
}

interface MonitoringEnvelope<TData = unknown> {
  type?: MonitoringEventType;
  data?: TData;
  timestamp?: string;
}

const SUPPORTED_EVENT_TYPES: MonitoringEventType[] = [
  "worker_stats_updated",
  "queue_stats_updated",
  "ticket_added",
  "ticket_status_changed",
];

const isSupportedMonitoringType = (value: string): value is MonitoringEventType =>
  SUPPORTED_EVENT_TYPES.includes(value as MonitoringEventType);

const buildManagerTopic = (managerId: string, selectedDate: string): string => {
  // if (!selectedDate) {
  //   return `manager/monitoring/${managerId}`;
  // }

  // const encodedDate = encodeURIComponent(selectedDate);
  // return `manager/monitoring/${managerId}?date=${encodedDate}`;
  return `manager/monitoring`;
};

export const ManagerMonitoringSSEConnection: React.FC<
  ManagerMonitoringSSEConnectionProps
> = ({ managerId, selectedDate, onUpdate, onError, onConnectionChange }) => {
  React.useEffect(() => {
    if (!managerId) {
      return;
    }

    const topic = buildManagerTopic(managerId, selectedDate);

    const subscription = subscribeToMercure<MonitoringEnvelope>({
      topics: [topic],
      eventTypes: SUPPORTED_EVENT_TYPES,
      parse: (raw, eventName) => {
        try {
          const parsed = JSON.parse(raw) as MonitoringEnvelope;
          if (!parsed.type && isSupportedMonitoringType(eventName)) {
            parsed.type = eventName;
          }
          return parsed;
        } catch (error) {
          onError?.(
            error instanceof Error
              ? error
              : new Error("Nie udało się przetworzyć danych z kanału monitoringu."),
          );
          return null;
        }
      },
      onMessage: ({ event, data }: MercurePayload<MonitoringEnvelope>) => {
        const resolvedType =
          data.type && isSupportedMonitoringType(data.type)
            ? data.type
            : isSupportedMonitoringType(event)
              ? event
              : null;

        if (!resolvedType) {
          return;
        }

        onUpdate({
          type: resolvedType,
          data: data.data,
          timestamp: data.timestamp ?? new Date().toISOString(),
        });
      },
      onError: (error) => {
        onError?.(error);
      },
      onConnectionChange,
      includeMessageEvent: false,
    });

    return () => {
      subscription.close();
    };
  }, [managerId, selectedDate, onUpdate, onError, onConnectionChange]);

  return null;
};

