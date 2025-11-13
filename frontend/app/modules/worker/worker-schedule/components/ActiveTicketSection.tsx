import * as React from "react";

import type { ScheduleTicket } from "~/api/worker/schedule";
import type { TicketMessage } from "~/api/tickets";
import { subscribeToMercure, type MercureSubscription } from "~/api/SSE/mercureClient";
import type { TicketEvent } from "~/api/SSE/TicketEvent";

const MERCURE_TOPIC_PREFIX = import.meta.env.VITE_MERCURE_TOPIC_PREFIX ?? "tickets";

type MessagesAction =
  | { type: "reset"; payload: TicketMessage[] }
  | { type: "upsert"; payload: TicketMessage };

const messagesReducer = (state: TicketMessage[], action: MessagesAction): TicketMessage[] => {
  switch (action.type) {
    case "reset":
      return [...action.payload].sort((a, b) => a.createdAt.localeCompare(b.createdAt));
    case "upsert": {
      const byId = new Map(state.map((message) => [message.id, message] as const));
      byId.set(action.payload.id, action.payload);
      return [...byId.values()].sort((a, b) => a.createdAt.localeCompare(b.createdAt));
    }
    default:
      return state;
  }
};

interface ActiveTicketSectionProps {
  ticket: ScheduleTicket | null;
  onStopWork: () => Promise<void> | void;
  onTicketClose?: () => Promise<void> | void;
  onNoteAdd: (ticketId: string, note: string) => Promise<boolean> | boolean;
  onMessageSend: (ticketId: string, message: string) => Promise<boolean> | boolean;
  isAddingNote: boolean;
  isSendingMessage: boolean;
  isChangingStatus: boolean;
  isClosing?: boolean;
  formatMinutes: (minutes: number) => string;
}

export const ActiveTicketSection: React.FC<ActiveTicketSectionProps> = ({
  ticket,
  onStopWork,
  onTicketClose,
  onNoteAdd,
  onMessageSend,
  isAddingNote,
  isSendingMessage,
  isChangingStatus,
  isClosing = false,
  formatMinutes,
}) => {
  const [noteContent, setNoteContent] = React.useState("");
  const [messageContent, setMessageContent] = React.useState("");
  const [messages, dispatchMessages] = React.useReducer(messagesReducer, ticket?.messages ?? [], () =>
    (ticket?.messages ?? []).sort((a, b) => a.createdAt.localeCompare(b.createdAt)),
  );

  React.useEffect(() => {
    React.startTransition(() => {
      setNoteContent("");
      setMessageContent("");
    });
  }, [ticket?.id]);

  React.useEffect(() => {
    dispatchMessages({ type: "reset", payload: ticket?.messages ?? [] });
  }, [ticket?.id, ticket?.messages]);

  const subscribeToTicketMessages = React.useCallback(
    (ticketId: string): MercureSubscription | null => {
      const topic = `${MERCURE_TOPIC_PREFIX}/${ticketId}`;

      let isActive = true;

      const subscription = subscribeToMercure<TicketEvent<TicketMessage>>({
        topics: [topic],
        withCredentials: true,
        onMessage: ({ data }) => {
          if (!isActive || data.data.ticketId !== ticketId || data.type !== "message") {
            return;
          }

          dispatchMessages({ type: "upsert", payload: data.data });
        },
        onError: (error) => {
          console.warn("Mercure connection issue for ticket messages", error);
        },
      });

      return {
        close: () => {
          isActive = false;
          subscription.close();
        },
        reconnect: () => {
          subscription.reconnect();
        },
      };
    },
    [],
  );

  React.useEffect(() => {
    const ticketId = ticket?.id;

    if (!ticketId) {
      return undefined;
    }

    const subscription = subscribeToTicketMessages(ticketId);

    return () => {
      subscription?.close();
    };
  }, [ticket?.id, subscribeToTicketMessages]);

  if (!ticket) {
    return (
      <section className="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-slate-300 bg-white p-6 text-center text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-300">
        <p>Wybierz ticket z listy, aby rozpocząć pracę.</p>
        <p>Po wybraniu ticketu zobaczysz tutaj szczegóły i możliwość dodawania notatek.</p>
      </section>
    );
  }

  const handleSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const success = await onNoteAdd(ticket.id, noteContent.trim());
    if (success) {
      setNoteContent("");
    }
  };

  const handleMessageSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const success = await onMessageSend(ticket.id, messageContent.trim());
    if (success) {
      setMessageContent("");
    }
  };

  const notes = ticket.notes ?? [];

  const resolveSenderLabel = (senderType: "client" | "worker", senderName?: string) => {
    if (senderName && senderName.trim().length > 0) {
      return senderName;
    }

    return senderType === "worker" ? "Pracownik" : "Klient";
  };

  return (
    <section
      className="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-950"
      data-testid="worker-schedule-active-ticket"
    >
      <header className="flex flex-col gap-1">
        <h2 className="text-lg font-semibold text-slate-900 dark:text-slate-100">
          Ticket w toku
        </h2>
        <p className="text-sm text-slate-500 dark:text-slate-400">
          {ticket.title}
        </p>
      </header>

      <dl className="grid gap-3 text-sm text-slate-600 dark:text-slate-300 sm:grid-cols-2">
        <div>
          <dt className="font-medium uppercase tracking-wide text-xs text-slate-500 dark:text-slate-400">
            Kategoria
          </dt>
          <dd>{ticket.category.name}</dd>
        </div>
        <div>
          <dt className="font-medium uppercase tracking-wide text-xs text-slate-500 dark:text-slate-400">
            Status
          </dt>
          <dd>{ticket.status}</dd>
        </div>
        <div>
          <dt className="font-medium uppercase tracking-wide text-xs text-slate-500 dark:text-slate-400">
            Czas spędzony
          </dt>
          <dd>{formatMinutes(ticket.timeSpent)}</dd>
        </div>
        <div>
          <dt className="font-medium uppercase tracking-wide text-xs text-slate-500 dark:text-slate-400">
            Zaplanowany czas
          </dt>
          <dd>{formatMinutes(ticket.estimatedTime)}</dd>
        </div>
      </dl>

      {ticket.client ? (
        <div className="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-900/40 dark:text-slate-300">
          <p className="font-semibold text-slate-700 dark:text-slate-200">
            Klient: {ticket.client.name}
          </p>
          {ticket.client.email ? (
            <p className="text-xs text-slate-500 dark:text-slate-400">
              Email: {ticket.client.email}
            </p>
          ) : null}
          {ticket.client.phone ? (
            <p className="text-xs text-slate-500 dark:text-slate-400">
              Telefon: {ticket.client.phone}
            </p>
          ) : null}
        </div>
      ) : null}

      <div className="flex flex-wrap items-center gap-2">
        <button
          type="button"
          onClick={() => {
            void onStopWork();
          }}
          className="rounded-md border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600 transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-300 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
          disabled={isChangingStatus || isClosing}
        >
          Zakończ pracę
        </button>
        {onTicketClose ? (
          <button
            type="button"
            onClick={() => {
              void onTicketClose();
            }}
            className="rounded-md border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-medium text-red-700 transition hover:bg-red-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-300 disabled:cursor-not-allowed disabled:opacity-60 dark:border-red-500/40 dark:bg-red-900/40 dark:text-red-200 dark:hover:bg-red-900/60"
            disabled={isChangingStatus || isClosing}
          >
            {isClosing ? "Zamykanie..." : "Zamknij ticket"}
          </button>
        ) : null}
      </div>

      <section className="flex flex-col gap-3">
        <h3 className="text-sm font-semibold text-slate-900 dark:text-slate-200">Wiadomości</h3>
        {messages.length > 0 ? (
          <ul className="flex max-h-48 flex-col gap-2 overflow-y-auto rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs text-slate-600 dark:border-slate-800 dark:bg-slate-900/40 dark:text-slate-300">
            {messages.map((message) => (
              <li key={message.id} className="flex flex-col gap-1">
                <p>{message.content}</p>
                <span className="text-[10px] uppercase text-slate-400 dark:text-slate-500">
                  {new Date(message.createdAt).toLocaleString("pl-PL")} —{" "}
                  {resolveSenderLabel(message.senderType, message.senderName)}
                </span>
              </li>
            ))}
          </ul>
        ) : (
          <p className="rounded-lg border border-dashed border-slate-200 p-3 text-xs text-slate-500 dark:border-slate-700 dark:text-slate-400">
            Brak wiadomości dla tego ticketa.
          </p>
        )}

        <form className="flex flex-col gap-2" onSubmit={handleMessageSubmit}>
          <label
            htmlFor="worker-schedule-message"
            className="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400"
          >
            Wyślij wiadomość
          </label>
          <textarea
            id="worker-schedule-message"
            className="min-h-[96px] rounded-md border border-slate-200 px-3 py-2 text-sm text-slate-700 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
            value={messageContent}
            onChange={(event) => setMessageContent(event.target.value)}
            placeholder="Przekaż informacje klientowi..."
          />
          <div className="flex justify-end">
            <button
              type="submit"
              className="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-indigo-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400 disabled:cursor-not-allowed disabled:bg-indigo-300 dark:bg-indigo-500 dark:hover:bg-indigo-400"
              disabled={isSendingMessage || messageContent.trim().length === 0}
            >
              {isSendingMessage ? "Wysyłanie..." : "Wyślij wiadomość"}
            </button>
          </div>
        </form>
      </section>

      <section className="flex flex-col gap-3">
        <h3 className="text-sm font-semibold text-slate-900 dark:text-slate-200">Notatki</h3>
        {notes.length > 0 ? (
          <ul className="flex max-h-48 flex-col gap-2 overflow-y-auto rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs text-slate-600 dark:border-slate-800 dark:bg-slate-900/40 dark:text-slate-300">
            {notes.map((note) => (
              <li key={note.id} className="flex flex-col gap-1">
                <p>{note.content}</p>
                <span className="text-[10px] uppercase text-slate-400 dark:text-slate-500">
                  {new Date(note.createdAt).toLocaleString("pl-PL")} — {note.createdBy}
                </span>
              </li>
            ))}
          </ul>
        ) : (
          <p className="rounded-lg border border-dashed border-slate-200 p-3 text-xs text-slate-500 dark:border-slate-700 dark:text-slate-400">
            Brak notatek dla tego ticketa.
          </p>
        )}

        <form className="flex flex-col gap-2" onSubmit={handleSubmit}>
          <label
            htmlFor="worker-schedule-note"
            className="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400"
          >
            Dodaj notatkę
          </label>
          <textarea
            id="worker-schedule-note"
            className="min-h-[96px] rounded-md border border-slate-200 px-3 py-2 text-sm text-slate-700 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
            value={noteContent}
            onChange={(event) => setNoteContent(event.target.value)}
            placeholder="Zapisz ważne ustalenia z klientem..."
          />
          <div className="flex justify-end">
            <button
              type="submit"
              className="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-indigo-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400 disabled:cursor-not-allowed disabled:bg-indigo-300 dark:bg-indigo-500 dark:hover:bg-indigo-400"
              disabled={isAddingNote || noteContent.trim().length === 0}
            >
              {isAddingNote ? "Zapisywanie..." : "Dodaj notatkę"}
            </button>
          </div>
        </form>
      </section>
    </section>
  );
};


