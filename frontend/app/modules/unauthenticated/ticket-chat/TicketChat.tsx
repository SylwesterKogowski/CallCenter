import * as React from "react";

import { ApiError } from "~/api/http";
import {
  useSendTicketMessageMutation,
  useTicketDetailsQuery,
  type TicketMessage,
} from "~/api/tickets";

import { ConnectionStatus } from "./components/ConnectionStatus";
import { ErrorDisplay } from "./components/ErrorDisplay";
import { LoadingSpinner } from "./components/LoadingSpinner";
import { MessageInput } from "./components/MessageInput";
import { MessageList } from "./components/MessageList";
import { TicketStatusDisplay } from "./components/TicketStatusDisplay";
import { TypingIndicator } from "./components/TypingIndicator";
import type {
  ChatConnectionStatus,
  ChatErrors,
  TicketChatDetails,
  TicketChatMessage,
  TicketChatStatus,
  TicketChatTypingState,
} from "./types";

const MERCURE_HUB_URL = import.meta.env.VITE_MERCURE_HUB_URL ?? "/hub";
const MERCURE_TOPIC_PREFIX = import.meta.env.VITE_MERCURE_TOPIC_PREFIX ?? "tickets";
const MERCURE_TOKEN = import.meta.env.VITE_MERCURE_SUBSCRIBER_TOKEN;

const MESSAGE_MAX_LENGTH = 5000;
const TYPING_TIMEOUT_MS = 5000;

interface TicketStatusChangedEvent {
  ticketId: string;
  status: TicketChatStatus;
  updatedAt?: string;
}

interface TypingEventPayload {
  ticketId: string;
  workerId?: string;
  workerName?: string;
  isTyping: boolean;
}

interface ChatState {
  messages: TicketChatMessage[];
  ticket: TicketChatDetails | null;
  connectionStatus: ChatConnectionStatus;
  errors: ChatErrors;
}

type ChatAction =
  | { type: "hydrate"; ticket: TicketChatDetails; messages: TicketChatMessage[] }
  | { type: "append-message"; message: TicketChatMessage }
  | { type: "set-ticket-status"; status: TicketChatStatus; updatedAt?: string }
  | { type: "set-connection-status"; status: ChatConnectionStatus }
  | { type: "merge-errors"; errors: Partial<ChatErrors> }
  | { type: "clear-error"; field: keyof ChatErrors }
  | { type: "clear-errors" };

const initialChatState: ChatState = {
  messages: [],
  ticket: null,
  connectionStatus: "connecting",
  errors: {},
};

const noop = () => {};

const buildMercureUrl = (ticketId: string) => {
  if (typeof window === "undefined") {
    return null;
  }

  try {
    const url = new URL(MERCURE_HUB_URL, window.location.origin);
    url.searchParams.append("topic", `${MERCURE_TOPIC_PREFIX}/${ticketId}`);

    if (MERCURE_TOKEN) {
      url.searchParams.set("token", MERCURE_TOKEN);
    }

    return url.toString();
  } catch (error) {
    console.error("Cannot build Mercure URL", error);
    return null;
  }
};

const dedupeAndSortMessages = (
  messages: TicketChatMessage[],
  incoming: TicketChatMessage,
) => {
  const byId = new Map(messages.map((message) => [message.id, message] as const));
  byId.set(incoming.id, incoming);

  return [...byId.values()].sort((a, b) => a.createdAt.localeCompare(b.createdAt));
};

const parseJson = <T,>(value: string): T | null => {
  try {
    return JSON.parse(value) as T;
  } catch (error) {
    console.warn("Failed to parse SSE payload", error);
    return null;
  }
};

const chatReducer = (state: ChatState, action: ChatAction): ChatState => {
  switch (action.type) {
    case "hydrate":
      return {
        ...state,
        ticket: action.ticket,
        messages: action.messages,
      };
    case "append-message":
      return {
        ...state,
        messages: dedupeAndSortMessages(state.messages, action.message),
      };
    case "set-ticket-status":
      if (!state.ticket) {
        return state;
      }
      return {
        ...state,
        ticket: {
          ...state.ticket,
          status: action.status,
          updatedAt: action.updatedAt ?? state.ticket.updatedAt,
        },
      };
    case "set-connection-status":
      return {
        ...state,
        connectionStatus: action.status,
      };
    case "merge-errors":
      return {
        ...state,
        errors: {
          ...state.errors,
          ...action.errors,
        },
      };
    case "clear-error": {
      if (!state.errors[action.field]) {
        return state;
      }
      const nextErrors = { ...state.errors };
      delete nextErrors[action.field];
      return {
        ...state,
        errors: nextErrors,
      };
    }
    case "clear-errors":
      return {
        ...state,
        errors: {},
      };
    default:
      return state;
  }
};

export interface TicketChatProps {
  ticketId: string;
  onTicketStatusChange?: (status: TicketChatStatus) => void;
}

export const TicketChat: React.FC<TicketChatProps> = ({ ticketId, onTicketStatusChange }) => {
  const [state, dispatch] = React.useReducer(chatReducer, initialChatState);
  const [typingState, setTypingState] = React.useState<TicketChatTypingState>({
    isTyping: false,
  });

  const { messages, ticket, connectionStatus, errors } = state;

  const typingTimeoutRef = React.useRef<ReturnType<typeof setTimeout> | null>(null);
  const eventSourceRef = React.useRef<EventSource | null>(null);
  const reconnectRef = React.useRef<() => void>(noop);

  const ticketQuery = useTicketDetailsQuery(ticketId, {
    retry: false,
  });

  React.useEffect(() => {
    if (!ticketQuery.data) {
      return;
    }

    dispatch({
      type: "hydrate",
      ticket: ticketQuery.data.ticket,
      messages: ticketQuery.data.messages,
    });
  }, [ticketQuery.data]);

  const sendMessageMutation = useSendTicketMessageMutation({
    onSuccess: (response) => {
      dispatch({ type: "append-message", message: response.message });
      dispatch({ type: "clear-error", field: "message" });
      dispatch({ type: "clear-error", field: "api" });
    },
  });

  const resetTypingState = React.useCallback(() => {
    setTypingState({ isTyping: false });
    if (typingTimeoutRef.current) {
      clearTimeout(typingTimeoutRef.current);
      typingTimeoutRef.current = null;
    }
  }, []);

  React.useEffect(() => {
    return () => {
      if (typingTimeoutRef.current) {
        clearTimeout(typingTimeoutRef.current);
      }
    };
  }, []);

  const handleTypingEvent = React.useCallback(
    (payload: TypingEventPayload) => {
      if (payload.ticketId !== ticketId) {
        return;
      }

      if (!payload.isTyping) {
        resetTypingState();
        return;
      }

      setTypingState({ isTyping: true, workerName: payload.workerName });

      if (typingTimeoutRef.current) {
        clearTimeout(typingTimeoutRef.current);
      }

      typingTimeoutRef.current = setTimeout(() => {
        resetTypingState();
      }, TYPING_TIMEOUT_MS);
    },
    [resetTypingState, ticketId],
  );

  const handleStatusEvent = React.useCallback(
    (payload: TicketStatusChangedEvent) => {
      if (payload.ticketId !== ticketId || !payload.status) {
        return;
      }

      dispatch({
        type: "set-ticket-status",
        status: payload.status,
        updatedAt: payload.updatedAt,
      });
      onTicketStatusChange?.(payload.status);
    },
    [dispatch, onTicketStatusChange, ticketId],
  );

  const handleMessageEvent = React.useCallback(
    (payload: TicketMessage) => {
      if (payload.ticketId !== ticketId) {
        return;
      }

      dispatch({ type: "append-message", message: payload });
    },
    [dispatch, ticketId],
  );

  const disconnectEventSource = React.useCallback(() => {
    eventSourceRef.current?.close();
    eventSourceRef.current = null;
    dispatch({ type: "set-connection-status", status: "disconnected" });
  }, [dispatch]);

  const connectToMercure = React.useCallback(() => {
    if (!ticketId) {
      return;
    }

    if (typeof window === "undefined" || typeof window.EventSource === "undefined") {
      dispatch({
        type: "merge-errors",
        errors: { connection: "Twoja przeglądarka nie wspiera połączenia w czasie rzeczywistym." },
      });
      dispatch({ type: "set-connection-status", status: "error" });
      return;
    }

    const url = buildMercureUrl(ticketId);

    if (!url) {
      dispatch({
        type: "merge-errors",
        errors: { connection: "Nie udało się nawiązać połączenia z serwerem powiadomień." },
      });
      dispatch({ type: "set-connection-status", status: "error" });
      return;
    }

    disconnectEventSource();
    dispatch({ type: "set-connection-status", status: "connecting" });
    dispatch({ type: "clear-error", field: "connection" });

    const EventSourceImpl = window.EventSource;
    const eventSource = new EventSourceImpl(url, { withCredentials: true });

    eventSource.onopen = () => {
      dispatch({ type: "set-connection-status", status: "connected" });
      dispatch({ type: "clear-error", field: "connection" });
    };

    eventSource.onerror = () => {
      dispatch({ type: "set-connection-status", status: "error" });
      dispatch({
        type: "merge-errors",
        errors: {
          connection:
            "Połączenie z serwerem powiadomień zostało przerwane. Spróbuj ponownie za chwilę.",
        },
      });
    };

    eventSource.addEventListener("message", (event) => {
      const parsed = parseJson<TicketMessage>((event as MessageEvent<string>).data);
      if (parsed) {
        handleMessageEvent(parsed);
      }
    });

    eventSource.addEventListener("ticket_status_changed", (event) => {
      const parsed = parseJson<TicketStatusChangedEvent>((event as MessageEvent<string>).data);
      if (parsed) {
        handleStatusEvent(parsed);
      }
    });

    eventSource.addEventListener("typing", (event) => {
      const parsed = parseJson<TypingEventPayload>((event as MessageEvent<string>).data);
      if (parsed) {
        handleTypingEvent(parsed);
      }
    });

    eventSourceRef.current = eventSource;
  }, [
    disconnectEventSource,
    dispatch,
    handleMessageEvent,
    handleStatusEvent,
    handleTypingEvent,
    ticketId,
  ]);

  React.useEffect(() => {
    reconnectRef.current = connectToMercure;
  }, [connectToMercure]);

  React.useEffect(() => {
    if (!ticketId) {
      return noop;
    }

    connectToMercure();

    return () => {
      disconnectEventSource();
    };
  }, [connectToMercure, disconnectEventSource, ticketId]);

  React.useEffect(() => {
    return () => {
      disconnectEventSource();
    };
  }, [disconnectEventSource]);

  const handleRetryConnection = () => {
    reconnectRef.current?.();
  };

  const extractMessageError = (payload: unknown) => {
    if (!payload || typeof payload !== "object") {
      return undefined;
    }

    const record = payload as Record<string, unknown>;

    if (typeof record.message === "string") {
      return record.message;
    }

    if (typeof record.error === "string") {
      return record.error;
    }

    if (record.errors && typeof record.errors === "object") {
      const errorsRecord = record.errors as Record<string, unknown>;
      if (typeof errorsRecord.content === "string") {
        return errorsRecord.content;
      }
    }

    return undefined;
  };

  const handleSendMessage = async (rawContent: string) => {
    const content = rawContent.trim();

    if (content.length === 0) {
      dispatch({
        type: "merge-errors",
        errors: { message: "Wiadomość nie może być pusta." },
      });
      return;
    }

    if (content.length > MESSAGE_MAX_LENGTH) {
      dispatch({
        type: "merge-errors",
        errors: { message: `Wiadomość przekracza maksymalną długość ${MESSAGE_MAX_LENGTH} znaków.` },
      });
      return;
    }

    try {
      dispatch({ type: "clear-error", field: "message" });
      dispatch({ type: "clear-error", field: "api" });
      await sendMessageMutation.mutateAsync({
        ticketId,
        content,
      });
    } catch (error) {
      if (error instanceof ApiError) {
        const message = extractMessageError(error.payload) ?? error.message;
        dispatch({
          type: "merge-errors",
          errors: {
            message,
            api: error.message,
          },
        });
        return;
      }

      if (error instanceof Error) {
        dispatch({
          type: "merge-errors",
          errors: { message: error.message },
        });
        return;
      }

      dispatch({
        type: "merge-errors",
        errors: { message: "Nie udało się wysłać wiadomości. Spróbuj ponownie." },
      });
    }
  };

  const clearGeneralErrors = () => {
    dispatch({ type: "clear-errors" });
  };

  const isTicketClosed = ticket?.status === "closed";
  const messageError = errors.message;

  return (
    <div className="mx-auto max-w-3xl space-y-6 rounded-xl border border-slate-200 bg-slate-50 p-6 shadow-sm dark:border-slate-700 dark:bg-slate-950">
      <header className="space-y-2">
        <h1 className="text-2xl font-bold text-slate-900 dark:text-slate-100">Czat z zespołem wsparcia</h1>
        <p className="text-sm text-slate-600 dark:text-slate-300">
          Kontynuuj rozmowę dotyczącą stworzonego ticketa. Wysyłaj wiadomości i otrzymuj odpowiedzi w czasie rzeczywistym.
        </p>
      </header>

      <ConnectionStatus
        status={connectionStatus}
        error={errors.connection}
        onRetry={connectionStatus === "error" || connectionStatus === "disconnected" ? handleRetryConnection : undefined}
      />

      {ticketQuery.isLoading ? (
        <LoadingSpinner message="Wczytujemy historię rozmowy..." />
      ) : null}

      {ticketQuery.isError ? (
        <ErrorDisplay
          errors={{
            ...errors,
            general:
              errors.general ??
              "Nie udało się pobrać danych ticketa. Spróbuj ponownie lub skontaktuj się z obsługą techniczną.",
          }}
          onDismiss={clearGeneralErrors}
        />
      ) : (
        <ErrorDisplay errors={errors} onDismiss={clearGeneralErrors} />
      )}

      {ticket ? <TicketStatusDisplay ticket={ticket} /> : null}

      <section className="space-y-4" aria-label="Czat">
        <MessageList messages={messages} ticketId={ticketId} />

        <TypingIndicator isTyping={typingState.isTyping} workerName={typingState.workerName} />

        <MessageInput
          onSend={handleSendMessage}
          isLoading={sendMessageMutation.isPending}
          isDisabled={isTicketClosed}
          maxLength={MESSAGE_MAX_LENGTH}
          error={messageError}
          placeholder={isTicketClosed ? "Ticket jest zamknięty. Nie można wysyłać kolejnych wiadomości." : undefined}
        />

        {isTicketClosed ? (
          <p className="text-sm text-amber-700 dark:text-amber-300" role="status" aria-live="polite">
            Ticket został zamknięty. Wysyłanie nowych wiadomości nie jest możliwe.
          </p>
        ) : null}
      </section>

      <footer className="text-xs text-slate-500 dark:text-slate-400">
        Pola formularza są dostępne z klawiatury. Enter wysyła wiadomość, a Shift+Enter dodaje nową linię.
      </footer>
    </div>
  );
};
