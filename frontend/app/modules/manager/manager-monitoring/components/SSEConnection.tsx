import * as React from "react";

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

const resolveEventsUrl = (managerId: string, selectedDate: string): string => {
  const baseFromEnv = import.meta.env.VITE_API_URL;
  const base =
    (baseFromEnv && baseFromEnv.length > 0 ? baseFromEnv : undefined) ??
    (typeof window !== "undefined" ? window.location.origin : "http://localhost");

  const sanitizedBase = base.replace(/\/$/, "");
  const url = new URL(`${sanitizedBase}/events/manager/monitoring/${managerId}`);
  url.searchParams.set("date", selectedDate);
  return url.toString();
};

export const ManagerMonitoringSSEConnection: React.FC<
  ManagerMonitoringSSEConnectionProps
> = ({ managerId, selectedDate, onUpdate, onError, onConnectionChange }) => {
  const eventSourceRef = React.useRef<EventSource | null>(null);

  React.useEffect(() => {
    if (typeof window === "undefined" || typeof window.EventSource === "undefined") {
      return;
    }

    const EventSourceImpl = window.EventSource;
    const url = resolveEventsUrl(managerId, selectedDate);
    const eventSource = new EventSourceImpl(url, { withCredentials: true });
    eventSourceRef.current = eventSource;

    const eventTypes: MonitoringEventType[] = [
      "worker_stats_updated",
      "queue_stats_updated",
      "ticket_added",
      "ticket_status_changed",
    ];

    const handleMessage = (event: MessageEvent<string>) => {
      try {
        const payload = JSON.parse(event.data) as Partial<MonitoringUpdate>;
        if (!payload || typeof payload.type !== "string") {
          return;
        }

        onUpdate({
          type: payload.type as MonitoringEventType,
          data: payload.data,
          timestamp: payload.timestamp ?? new Date().toISOString(),
        });
      } catch (error) {
        if (onError) {
          onError(
            error instanceof Error
              ? error
              : new Error("Nie udało się przetworzyć danych SSE."),
          );
        }
      }
    };

    eventTypes.forEach((eventName) => {
      eventSource.addEventListener(eventName, handleMessage);
    });

    eventSource.onmessage = handleMessage;

    eventSource.onopen = () => {
      onConnectionChange?.(true);
    };

    eventSource.onerror = () => {
      onConnectionChange?.(false);
      if (onError) {
        onError(new Error("Połączenie z serwerem SSE zostało przerwane."));
      }
    };

    return () => {
      eventTypes.forEach((eventName) => {
        eventSource.removeEventListener(eventName, handleMessage);
      });
      eventSource.close();
      eventSourceRef.current = null;
    };
  }, [managerId, onUpdate, onError, onConnectionChange, selectedDate]);

  return null;
};


