import * as React from "react";

import { subscribeToMercure, type MercurePayload } from "~/api/SSE/mercureClient";

export type ScheduleEventType =
  | "ticket_added"
  | "ticket_updated"
  | "ticket_removed"
  | "status_changed"
  | "time_updated";

export interface ScheduleUpdate {
  type: ScheduleEventType;
  ticketId: string;
  data: unknown;
  timestamp: string;
}

export interface WorkerScheduleSSEConnectionProps {
  workerId: string;
  onScheduleUpdate: (update: ScheduleUpdate) => void;
  onError?: (error: Error) => void;
}

interface ScheduleEnvelope {
  type?: ScheduleEventType;
  ticketId?: string;
  data?: unknown;
  timestamp?: string;
}

const SUPPORTED_EVENT_TYPES: ScheduleEventType[] = [
  "ticket_added",
  "ticket_updated",
  "ticket_removed",
  "status_changed",
  "time_updated",
];

const isSupportedEventType = (value: string): value is ScheduleEventType =>
  SUPPORTED_EVENT_TYPES.includes(value as ScheduleEventType);

export const WorkerScheduleSSEConnection: React.FC<WorkerScheduleSSEConnectionProps> = ({
  workerId,
  onScheduleUpdate,
  onError,
}) => {
  React.useEffect(() => {
    if (!workerId) {
      return;
    }

    const subscription = subscribeToMercure<ScheduleEnvelope>({
      topics: [`worker/schedule/${workerId}`],
      eventTypes: SUPPORTED_EVENT_TYPES,
      parse: (raw) => {
        try {
          return JSON.parse(raw) as ScheduleEnvelope;
        } catch (error) {
          onError?.(
            error instanceof Error
              ? error
              : new Error("Nie udało się przetworzyć danych z kanału Mercure."),
          );
          return null;
        }
      },
      onMessage: ({ event, data }: MercurePayload<ScheduleEnvelope>) => {
        const resolvedType =
          data.type && isSupportedEventType(data.type)
            ? data.type
            : isSupportedEventType(event)
              ? event
              : null;

        if (!resolvedType) {
          return;
        }

        onScheduleUpdate({
          type: resolvedType,
          ticketId: data.ticketId ?? "",
          data: data.data,
          timestamp: data.timestamp ?? new Date().toISOString(),
        });
      },
      onError: (error) => {
        onError?.(error);
      },
      includeMessageEvent: false,
    });

    return () => {
      subscription.close();
    };
  }, [workerId, onScheduleUpdate, onError]);

  return null;
};