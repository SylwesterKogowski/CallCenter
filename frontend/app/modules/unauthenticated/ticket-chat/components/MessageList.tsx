import * as React from "react";

import type { TicketChatMessage } from "../types";

export interface MessageListProps {
  messages: TicketChatMessage[];
  ticketId: string;
}

const formatTimestamp = (value: string) => {
  try {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
      return value;
    }

    return new Intl.DateTimeFormat("pl-PL", {
      dateStyle: "short",
      timeStyle: "short",
    }).format(date);
  } catch {
    return value;
  }
};

const isWorkerMessage = (message: TicketChatMessage) => message.senderType === "worker";

export const MessageList: React.FC<MessageListProps> = ({ messages, ticketId }) => {
  const containerRef = React.useRef<HTMLDivElement>(null);
  const shouldAutoScrollRef = React.useRef(true);

  React.useEffect(() => {
    const container = containerRef.current;
    if (!container) {
      return;
    }

    if (!shouldAutoScrollRef.current) {
      return;
    }

    if (typeof container.scrollTo === "function") {
      container.scrollTo({ top: container.scrollHeight, behavior: "smooth" });
    } else {
      container.scrollTop = container.scrollHeight;
    }
  }, [messages]);

  const handleScroll = React.useCallback(() => {
    const container = containerRef.current;
    if (!container) {
      return;
    }

    const distanceToBottom = container.scrollHeight - container.scrollTop - container.clientHeight;
    shouldAutoScrollRef.current = distanceToBottom < 64;
  }, []);

  return (
    <div
      ref={containerRef}
      onScroll={handleScroll}
      role="region"
      aria-live="polite"
      aria-atomic="false"
      aria-label="Historia wiadomości"
      className="h-80 overflow-y-auto rounded-lg border border-slate-200 bg-white p-4 shadow-inner focus:outline-none dark:border-slate-700 dark:bg-slate-950"
      tabIndex={0}
    >
      {messages.length === 0 ? (
        <p className="text-sm text-slate-500 dark:text-slate-400" data-testid="empty-messages">
          Nie ma jeszcze żadnych wiadomości w tym tickecie. Napisz pierwszą wiadomość, aby rozpocząć rozmowę.
        </p>
      ) : (
        <ul role="list" className="space-y-4">
          {messages.map((message) => {
            const worker = isWorkerMessage(message);
            const label = worker
              ? `Wiadomość od pracownika ${message.senderName ?? "Call Center"}`
              : "Twoja wiadomość";

            return (
              <li
                key={message.id}
                role="listitem"
                aria-label={label}
                id={`ticket-${ticketId}-message-${message.id}`}
                className={`flex ${worker ? "justify-start" : "justify-end"}`}
              >
                <article
                  className={`max-w-[85%] space-y-2 rounded-2xl px-4 py-3 text-sm shadow transition ${
                    worker
                      ? "bg-slate-100 text-slate-900 dark:bg-slate-800 dark:text-slate-50"
                      : "bg-emerald-500/90 text-white dark:bg-emerald-600"
                  }`}
                >
                  <header className="flex items-center justify-between gap-3 text-xs">
                    <span className="font-semibold">
                      {worker ? message.senderName ?? "Pracownik" : "Ty"}
                    </span>
                    <span className="text-slate-500 dark:text-slate-300">
                      {formatTimestamp(message.createdAt)}
                    </span>
                  </header>
                  <p className="whitespace-pre-wrap leading-relaxed text-sm" data-testid="chat-message">
                    {message.content}
                  </p>
                </article>
              </li>
            );
          })}
        </ul>
      )}
    </div>
  );
};
