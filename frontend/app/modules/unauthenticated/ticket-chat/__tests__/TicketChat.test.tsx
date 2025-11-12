import { act, render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { describe, expect, it, beforeEach, afterEach, vi } from "vitest";

import * as http from "~/api/http";
import { apiPaths } from "~/api/http";
import type {
  SendTicketMessageResponse,
  TicketDetailsResponse,
  TicketMessage,
} from "~/api/tickets";

import { TicketChat } from "../TicketChat";

type Listener = (event: MessageEvent<string>) => void;

class MockEventSource implements EventSource {
  static instances: MockEventSource[] = [];

  static CONNECTING = 0;
  static OPEN = 1;
  static CLOSED = 2;

  CONNECTING = MockEventSource.CONNECTING;
  OPEN = MockEventSource.OPEN;
  CLOSED = MockEventSource.CLOSED;

  readyState = MockEventSource.CONNECTING;
  url: string;
  withCredentials?: boolean;
  onopen: ((event: Event) => void) | null = null;
  onmessage: ((event: MessageEvent) => void) | null = null;
  onerror: ((event: Event) => void) | null = null;

  private listeners: Map<string, Set<Listener>> = new Map();

  constructor(url: string, init?: EventSourceInit) {
    this.url = url;
    this.withCredentials = init?.withCredentials;
    MockEventSource.instances.push(this);
  }

  addEventListener(type: string, listener: Listener): void {
    if (!this.listeners.has(type)) {
      this.listeners.set(type, new Set());
    }

    this.listeners.get(type)?.add(listener);
  }

  removeEventListener(type: string, listener: Listener): void {
    this.listeners.get(type)?.delete(listener);
  }

  close(): void {
    this.readyState = MockEventSource.CLOSED;
  }

  dispatch(type: string, payload: unknown) {
    const event = {
      data: JSON.stringify(payload),
      type,
    } as MessageEvent<string>;

    this.listeners.get(type)?.forEach((listener) => listener(event));

    if (type === "message") {
      this.onmessage?.(event as MessageEvent);
    }
  }
}

const createQueryClient = () =>
  new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  });

const renderChat = (ui: React.ReactNode) => {
  const queryClient = createQueryClient();

  const view = render(
    <QueryClientProvider client={queryClient}>{ui}</QueryClientProvider>,
  );

  return { view, queryClient };
};

describe("TicketChat", () => {
  const ticketId = "ticket-123";

  const baseResponse: TicketDetailsResponse = {
    ticket: {
      id: ticketId,
      clientId: "client-1",
      categoryId: "cat-sales",
      categoryName: "Sprzedaż",
      title: "Problem z połączeniem",
      description: "Nie mogę nawiązać połączenia od rana",
      status: "awaiting_response",
      createdAt: "2025-01-01T09:00:00Z",
      updatedAt: "2025-01-01T09:05:00Z",
    },
    messages: [
      {
        id: "msg-1",
        ticketId,
        senderType: "client",
        content: "Dzień dobry, proszę o pomoc",
        createdAt: "2025-01-01T09:00:00Z",
      },
      {
        id: "msg-2",
        ticketId,
        senderType: "worker",
        senderId: "worker-1",
        senderName: "Jan Kowalski",
        content: "W czym mogę pomóc?",
        createdAt: "2025-01-01T09:02:00Z",
      },
    ],
  };

  beforeEach(() => {
    vi.restoreAllMocks();
    MockEventSource.instances = [];
    vi.stubGlobal("EventSource", MockEventSource);
  });

  afterEach(() => {
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
  });

  it("renders ticket chat with initial data", async () => {
    const fetchSpy = vi.spyOn(http, "apiFetch").mockResolvedValueOnce(baseResponse);

    renderChat(<TicketChat ticketId={ticketId} />);

    await waitFor(() => expect(fetchSpy).toHaveBeenCalledTimes(1));

    expect(await screen.findByText(/Czat z zespołem wsparcia/i)).toBeInTheDocument();
    expect(await screen.findByText(/Problem z połączeniem/i)).toBeInTheDocument();
    expect(await screen.findByText(/Dzień dobry, proszę o pomoc/i)).toBeInTheDocument();

    expect(fetchSpy).toHaveBeenCalledTimes(1);

    await waitFor(() => expect(MockEventSource.instances.length).toBe(1));
    const eventSourceUrl = MockEventSource.instances[0].url;
    expect(decodeURIComponent(eventSourceUrl)).toContain(`topic=tickets/${ticketId}`);
  });

  it("sends a new message and displays it", async () => {
    const newMessage: TicketMessage = {
      id: "msg-3",
      ticketId,
      senderType: "client",
      content: "Dziękuję za szybką odpowiedź",
      createdAt: "2025-01-01T09:03:00Z",
    };

    const updatedResponse: TicketDetailsResponse = {
      ...baseResponse,
      messages: [...baseResponse.messages, newMessage],
    };

    const fetchSpy = vi.spyOn(http, "apiFetch");

    fetchSpy.mockResolvedValueOnce(baseResponse);
    fetchSpy.mockImplementationOnce(async (options) => {
      expect(options.path).toBe(apiPaths.ticketMessages(ticketId));
      expect(options.method).toBe("POST");
      expect(options.body).toEqual({ content: newMessage.content });

      return { message: newMessage } satisfies SendTicketMessageResponse;
    });
    fetchSpy.mockResolvedValueOnce(updatedResponse);

    renderChat(<TicketChat ticketId={ticketId} />);

    await waitFor(() => expect(fetchSpy).toHaveBeenCalledTimes(1));

    await screen.findByText(/W czym mogę pomóc/i);

    const textarea = screen.getByLabelText(/Pole wprowadzania wiadomości/i);

    await userEvent.type(textarea, newMessage.content);
    await userEvent.click(screen.getByRole("button", { name: /Wyślij wiadomość/i }));

    await waitFor(() => expect(fetchSpy).toHaveBeenCalledTimes(2));
    expect(await screen.findByText(newMessage.content)).toBeInTheDocument();
    expect((textarea as HTMLTextAreaElement).value).toBe("");
  });

  it("handles SSE updates for messages and status changes", async () => {
    vi.spyOn(http, "apiFetch").mockResolvedValue(baseResponse);

    const onStatusChange = vi.fn();

    renderChat(<TicketChat ticketId={ticketId} onTicketStatusChange={onStatusChange} />);

    await waitFor(() => expect(http.apiFetch).toHaveBeenCalled());

    await screen.findByText(/Problem z połączeniem/i);

    const [eventSource] = MockEventSource.instances;
    expect(eventSource).toBeDefined();

    await act(async () => {
      eventSource.onopen?.(new Event("open"));
    });

    const workerMessage: TicketMessage = {
      id: "msg-4",
      ticketId,
      senderType: "worker",
      senderId: "worker-2",
      senderName: "Anna Nowak",
      content: "Sprawdzamy konfiguracje lacza",
      createdAt: "2025-01-01T09:04:00Z",
    };

    await act(async () => {
      eventSource.dispatch("message", workerMessage);
    });

    expect(await screen.findByText(workerMessage.content)).toBeInTheDocument();

    const statusPayload = {
      ticketId,
      status: "in_progress",
      updatedAt: "2025-01-01T09:05:30Z",
    } as const;

    await act(async () => {
      eventSource.dispatch("ticket_status_changed", statusPayload);
    });

    await waitFor(() => expect(onStatusChange).toHaveBeenCalledWith("in_progress"));
    expect(await screen.findByText(/W toku/i)).toBeInTheDocument();

    await act(async () => {
      eventSource.dispatch("typing", {
        ticketId,
        workerName: "Anna Nowak",
        isTyping: true,
      });
    });

    expect(await screen.findByText(/Anna Nowak pisze/i)).toBeInTheDocument();
  });
});
