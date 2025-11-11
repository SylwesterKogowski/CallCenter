import * as React from "react";

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

const resolveEventsUrl = (workerId: string): string => {
  const baseFromEnv = import.meta.env.VITE_API_URL;
  const base =
    (baseFromEnv && baseFromEnv.length > 0 ? baseFromEnv : undefined) ??
    (typeof window !== "undefined" ? window.location.origin : "http://localhost");

  const sanitizedBase = base.replace(/\/$/, "");
  return `${sanitizedBase}/events/worker/schedule/${workerId}`;
};

export const WorkerScheduleSSEConnection: React.FC<WorkerScheduleSSEConnectionProps> = ({
  workerId,
  onScheduleUpdate,
  onError,
}) => {
  const eventSourceRef = React.useRef<EventSource | null>(null);

  React.useEffect(() => {
    if (typeof window === "undefined" || typeof window.EventSource === "undefined") {
      return;
    }

    const url = resolveEventsUrl(workerId);
    const EventSourceImpl = window.EventSource;
    const eventSource = new EventSourceImpl(url, { withCredentials: true });
    eventSourceRef.current = eventSource;

    const eventTypes: ScheduleEventType[] = [
      "ticket_added",
      "ticket_updated",
      "ticket_removed",
      "status_changed",
      "time_updated",
    ];

    const handleUpdate = (event: MessageEvent) => {
      try {
        const payload = JSON.parse(event.data) as Partial<ScheduleUpdate>;
        if (!payload || !payload.type) {
          return;
        }
        onScheduleUpdate({
          type: payload.type as ScheduleEventType,
          ticketId: payload.ticketId ?? "",
          data: payload.data,
          timestamp: payload.timestamp ?? new Date().toISOString(),
        });
      } catch (error) {
        if (onError) {
          onError(error instanceof Error ? error : new Error("Nie udało się przetworzyć danych SSE."));
        }
      }
    };

    eventTypes.forEach((eventName) => {
      eventSource.addEventListener(eventName, handleUpdate);
    });
    eventSource.onmessage = handleUpdate;

    eventSource.onerror = () => {
      if (onError) {
        onError(new Error("Połączenie z serwerem SSE zostało przerwane."));
      }
    };

    return () => {
      eventTypes.forEach((eventName) => {
        eventSource.removeEventListener(eventName, handleUpdate);
      });
      eventSource.close();
      eventSourceRef.current = null;
    };
  }, [workerId, onScheduleUpdate, onError]);

  return null;
};


