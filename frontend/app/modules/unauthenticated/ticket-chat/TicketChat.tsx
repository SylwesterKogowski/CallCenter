import * as React from "react";

import { ApiError } from "~/api/http";
import {
  useSendTicketMessageMutation,
  useTicketDetailsQuery,
  type TicketMessage,
} from "~/api/tickets";
import {
  subscribeToMercure,
  type MercurePayload,
  type MercureSubscription,
} from "~/api/SSE/mercureClient";

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

const MERCURE_TOPIC_PREFIX = import.meta.env.VITE_MERCURE_TOPIC_PREFIX ?? "tickets";

const MESSAGE_MAX_LENGTH = 5000;
const TYPING_TIMEOUT_MS = 5000;

const resolveConnectionErrorMessage = (error: Error) => {
  switch (error.message) {
    case "Mercure SSE is not supported in this environment.":
      return "Twoja przeglądarka nie wspiera połączenia w czasie rzeczywistym.";
    case "Unable to resolve Mercure hub URL.":
      return "Nie udało się nawiązać połączenia z serwerem powiadomień.";
    default:
      return "Połączenie z serwerem powiadomień zostało przerwane. Spróbuj ponownie za chwilę.";
  }
};

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
  const mercureSubscriptionRef = React.useRef<MercureSubscription | null>(null);
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

  const handleMercureMessage = React.useCallback(
    (payload: MercurePayload<unknown>) => {
      switch (payload.event) {
        case "message": {
          if (payload.data && typeof payload.data === "object") {
            handleMessageEvent(payload.data as TicketMessage);
          }
          break;
        }
        case "ticket_status_changed": {
          if (payload.data && typeof payload.data === "object") {
            handleStatusEvent(payload.data as TicketStatusChangedEvent);
          }
          break;
        }
        case "typing": {
          if (payload.data && typeof payload.data === "object") {
            handleTypingEvent(payload.data as TypingEventPayload);
          }
          break;
        }
        default:
          break;
      }
    },
    [handleMessageEvent, handleStatusEvent, handleTypingEvent],
  );

  const disconnectMercure = React.useCallback(() => {
    mercureSubscriptionRef.current?.close();
    mercureSubscriptionRef.current = null;
    dispatch({ type: "set-connection-status", status: "disconnected" });
  }, [dispatch]);

  const connectToMercure = React.useCallback(() => {
    if (!ticketId) {
      return;
    }

    disconnectMercure();
    dispatch({ type: "set-connection-status", status: "connecting" });
    dispatch({ type: "clear-error", field: "connection" });

    const topic = `${MERCURE_TOPIC_PREFIX}/${ticketId}`;

    const subscription = subscribeToMercure<unknown>({
      topics: [topic],
      eventTypes: ["ticket_status_changed", "typing"],
      withCredentials: true,
      parse: (raw) => parseJson<unknown>(raw),
      onMessage: handleMercureMessage,
      onConnectionChange: (isConnected) => {
        if (isConnected) {
          dispatch({ type: "set-connection-status", status: "connected" });
          dispatch({ type: "clear-error", field: "connection" });
        } else {
          dispatch({ type: "set-connection-status", status: "disconnected" });
        }
      },
      onError: (error) => {
        dispatch({ type: "set-connection-status", status: "error" });
        dispatch({
          type: "merge-errors",
          errors: {
            connection: resolveConnectionErrorMessage(error),
          },
        });
      },
    });

    mercureSubscriptionRef.current = subscription;
  }, [
    disconnectMercure,
    dispatch,
    handleMercureMessage,
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
      disconnectMercure();
    };
  }, [connectToMercure, disconnectMercure, ticketId]);

  React.useEffect(() => {
    return () => {
      disconnectMercure();
    };
  }, [disconnectMercure]);

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
