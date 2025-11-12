import "@testing-library/jest-dom";
import { act, render, screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

import * as http from "~/api/http";
import { apiPaths } from "~/api/http";
import type {
  WorkerScheduleResponse,
  WorkerWorkStatusResponse,
} from "~/api/worker/schedule";

import { WorkerSchedule } from "../WorkerSchedule";

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

const baseScheduleResponse: WorkerScheduleResponse = {
  schedule: [
    {
      date: "2025-01-15",
      totalTimeSpent: 90,
      tickets: [
        {
          id: "ticket-1",
          title: "Instalacja oprogramowania",
          category: { id: "cat-1", name: "Wsparcie IT", description: "", defaultResolutionTimeMinutes: 60 },
          status: "in_progress",
          timeSpent: 30,
          estimatedTime: 60,
          scheduledDate: "2025-01-15",
          isActive: true,
          notes: [
            {
              id: "note-1",
              content: "Sprawdzono dostęp do serwera.",
              createdAt: "2025-01-15T09:15:00Z",
              createdBy: "worker-123",
            },
          ],
          client: {
            id: "client-1",
            name: "Jan Kowalski",
            email: "jan@example.com",
            phone: "+48123123123",
          },
        },
        {
          id: "ticket-2",
          title: "Audyt bezpieczeństwa",
          category: { id: "cat-2", name: "Bezpieczeństwo", description: "", defaultResolutionTimeMinutes: 90 },
          status: "waiting",
          timeSpent: 0,
          estimatedTime: 90,
          scheduledDate: "2025-01-15",
        },
      ],
    },
    {
      date: "2025-01-16",
      totalTimeSpent: 45,
      tickets: [
        {
          id: "ticket-3",
          title: "Konfiguracja call center",
          category: { id: "cat-1", name: "Wsparcie IT", description: "", defaultResolutionTimeMinutes: 60 },
          status: "waiting",
          timeSpent: 45,
          estimatedTime: 120,
          scheduledDate: "2025-01-16",
        },
      ],
    },
  ],
  activeTicket: {
    id: "ticket-1",
    title: "Instalacja oprogramowania",
    category: { id: "cat-1", name: "Wsparcie IT", description: "", defaultResolutionTimeMinutes: 60 },
    status: "in_progress",
    timeSpent: 30,
    estimatedTime: 60,
    scheduledDate: "2025-01-15",
    notes: [
      {
        id: "note-1",
        content: "Sprawdzono dostęp do serwera.",
        createdAt: "2025-01-15T09:15:00Z",
        createdBy: "worker-123",
      },
    ],
    client: {
      id: "client-1",
      name: "Jan Kowalski",
      email: "jan@example.com",
      phone: "+48123123123",
    },
  },
};

const baseWorkStatusResponse: WorkerWorkStatusResponse = {
  status: {
    level: "normal",
    message: "Masz zbalansowane obciążenie pracą.",
    ticketsCount: 3,
    timeSpent: 135,
    timePlanned: 210,
  },
  todayStats: {
    date: "2025-01-15",
    ticketsCount: 2,
    timeSpent: 90,
    timePlanned: 150,
    completedTickets: 1,
    inProgressTickets: 1,
    waitingTickets: 0,
  },
};

const renderSchedule = (queryClient: QueryClient) => {
  return render(
    <QueryClientProvider client={queryClient}>
      <WorkerSchedule workerId="worker-123" />
    </QueryClientProvider>,
  );
};

describe("WorkerSchedule", () => {
  beforeEach(() => {
    vi.restoreAllMocks();
    MockEventSource.instances = [];
    vi.stubGlobal("EventSource", MockEventSource);
  });

  afterEach(() => {
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
  });

  it("renders schedule and allows selecting a ticket as active", async () => {
    let currentSchedule: WorkerScheduleResponse = JSON.parse(
      JSON.stringify(baseScheduleResponse),
    );

    const fetchSpy = vi.spyOn(http, "apiFetch").mockImplementation(async (options) => {
      if (options.path === apiPaths.workerSchedule && (options.method ?? "GET") === "GET") {
        return currentSchedule;
      }

      if (options.path === apiPaths.workerWorkStatus) {
        return baseWorkStatusResponse;
      }

      if (options.path === apiPaths.workerTicketStatus("ticket-1") && options.method === "POST") {
        const body = options.body as { status: string };
        currentSchedule = JSON.parse(JSON.stringify(currentSchedule));
        currentSchedule.schedule = currentSchedule.schedule.map((day) => ({
          ...day,
          tickets: day.tickets.map((ticket) =>
            ticket.id === "ticket-1" ? { ...ticket, status: body.status as typeof ticket.status } : ticket,
          ),
        }));
        if (currentSchedule.activeTicket?.id === "ticket-1") {
          currentSchedule.activeTicket = {
            ...currentSchedule.activeTicket,
            status: body.status as typeof currentSchedule.activeTicket.status,
          };
        }
        return {
          ticket: {
            id: "ticket-1",
            status: body.status,
            updatedAt: new Date().toISOString(),
          },
        };
      }

      if (options.path === apiPaths.workerTicketStatus("ticket-3") && options.method === "POST") {
        const body = options.body as { status: string };
        currentSchedule = JSON.parse(JSON.stringify(currentSchedule));
        currentSchedule.schedule = currentSchedule.schedule.map((day) => ({
          ...day,
          tickets: day.tickets.map((ticket) =>
            ticket.id === "ticket-3" ? { ...ticket, status: body.status as typeof ticket.status } : ticket,
          ),
        }));

        if (body.status === "in_progress") {
          const ticket = currentSchedule.schedule
            .flatMap((day) => day.tickets)
            .find((item) => item.id === "ticket-3");
          currentSchedule.activeTicket = ticket ?? null;
        }

        return {
          ticket: {
            id: "ticket-3",
            status: body.status,
            updatedAt: new Date().toISOString(),
          },
        };
      }

      throw new Error(`Unhandled API call ${options.path} (${options.method ?? "GET"})`);
    });

    const queryClient = createQueryClient();
    renderSchedule(queryClient);

    expect(await screen.findByText(/Twój grafik/i)).toBeInTheDocument();
    expect(await screen.findByText(/Instalacja oprogramowania/)).toBeInTheDocument();

    const nextDayButton = await screen.findByTestId("worker-schedule-calendar-day-2025-01-16");
    await userEvent.click(nextDayButton);

    const ticketCard = await screen.findByTestId("worker-schedule-ticket-ticket-3");
    const startButton = within(ticketCard).getByRole("button", { name: /Rozpocznij/i });
    await userEvent.click(startButton);

    await waitFor(() =>
      expect(
        screen.getByText(/Ticket "Konfiguracja call center" został ustawiony jako aktywny./i),
      ).toBeInTheDocument(),
    );

    expect(
      await screen.findByTestId("worker-schedule-active-ticket"),
    ).toHaveTextContent("Konfiguracja call center");

    expect(fetchSpy).toHaveBeenCalledWith(
      expect.objectContaining({
        path: apiPaths.workerTicketStatus("ticket-3"),
        method: "POST",
      }),
    );
  });

  it("allows adding notes and manual time to active ticket", async () => {
    let currentSchedule: WorkerScheduleResponse = JSON.parse(
      JSON.stringify(baseScheduleResponse),
    );

    const fetchSpy = vi.spyOn(http, "apiFetch").mockImplementation(async (options) => {
      if (options.path === apiPaths.workerSchedule && (options.method ?? "GET") === "GET") {
        return currentSchedule;
      }

      if (options.path === apiPaths.workerWorkStatus) {
        return baseWorkStatusResponse;
      }

      if (options.path === apiPaths.workerTicketTime("ticket-1") && options.method === "POST") {
        const body = options.body as { minutes: number };
        currentSchedule = JSON.parse(JSON.stringify(currentSchedule));
        currentSchedule.schedule = currentSchedule.schedule.map((day) => ({
          ...day,
          tickets: day.tickets.map((ticket) =>
            ticket.id === "ticket-1"
              ? {
                  ...ticket,
                  timeSpent: ticket.timeSpent + body.minutes,
                }
              : ticket,
          ),
        }));
        if (currentSchedule.activeTicket?.id === "ticket-1") {
          currentSchedule.activeTicket = {
            ...currentSchedule.activeTicket,
            timeSpent: (currentSchedule.activeTicket.timeSpent ?? 0) + body.minutes,
          };
        }
        return {
          ticket: {
            id: "ticket-1",
            timeSpent: (currentSchedule.activeTicket?.timeSpent ?? 0),
            updatedAt: new Date().toISOString(),
          },
        };
      }

      if (
        options.path === apiPaths.workerTicketNotes("ticket-1") &&
        options.method === "POST"
      ) {
        const body = options.body as { content: string };
        const note = {
          id: `note-${Math.random()}`,
          content: body.content,
          createdAt: new Date().toISOString(),
          createdBy: "worker-123",
        };
        currentSchedule = JSON.parse(JSON.stringify(currentSchedule));
        currentSchedule.schedule = currentSchedule.schedule.map((day) => ({
          ...day,
          tickets: day.tickets.map((ticket) =>
            ticket.id === "ticket-1"
              ? {
                  ...ticket,
                  notes: [...(ticket.notes ?? []), note],
                }
              : ticket,
          ),
        }));
        if (currentSchedule.activeTicket?.id === "ticket-1") {
          currentSchedule.activeTicket = {
            ...currentSchedule.activeTicket,
            notes: [...(currentSchedule.activeTicket.notes ?? []), note],
          };
        }
        return { note };
      }

      throw new Error(`Unhandled API call ${options.path} (${options.method ?? "GET"})`);
    });

    const queryClient = createQueryClient();
    renderSchedule(queryClient);

    const noteTextarea = await screen.findByLabelText(/Dodaj notatkę/i);
    await userEvent.type(noteTextarea, "Nowa notatka testowa");

    await userEvent.click(screen.getByRole("button", { name: /Dodaj notatkę/i }));

    await waitFor(() =>
      expect(screen.getByText("Nowa notatka testowa")).toBeInTheDocument(),
    );

    const minutesInput = screen.getByLabelText(/Dodaj czas ręcznie/i, { selector: "input" });
    await userEvent.clear(minutesInput);
    await userEvent.type(minutesInput, "5");

    await userEvent.selectOptions(
      screen.getByRole("combobox"),
      "phone_call",
    );

    await userEvent.click(screen.getByRole("button", { name: /Dodaj czas/i }));

    await waitFor(() =>
      expect(
        screen.getByTestId("worker-schedule-time-tracker"),
      ).toHaveTextContent("35 min"),
    );

    expect(fetchSpy).toHaveBeenCalledWith(
      expect.objectContaining({
        path: apiPaths.workerTicketTime("ticket-1"),
        method: "POST",
      }),
    );
  });

  it("revalidates queries when SSE event is received", async () => {
    let currentSchedule: WorkerScheduleResponse = JSON.parse(
      JSON.stringify(baseScheduleResponse),
    );

    const fetchSpy = vi.spyOn(http, "apiFetch").mockImplementation(async (options) => {
      if (options.path === apiPaths.workerSchedule && (options.method ?? "GET") === "GET") {
        return currentSchedule;
      }

      if (options.path === apiPaths.workerWorkStatus) {
        return baseWorkStatusResponse;
      }

      throw new Error(`Unhandled API call ${options.path} (${options.method ?? "GET"})`);
    });

    const queryClient = createQueryClient();
    renderSchedule(queryClient);

    expect(await screen.findByText(/Instalacja oprogramowania/)).toBeInTheDocument();

    const [eventSource] = MockEventSource.instances;
    expect(decodeURIComponent(eventSource.url)).toContain("topic=worker/schedule/worker-123");

    currentSchedule = JSON.parse(JSON.stringify(currentSchedule));
    currentSchedule.activeTicket = {
      ...currentSchedule.activeTicket!,
      timeSpent: (currentSchedule.activeTicket?.timeSpent ?? 0) + 10,
    };
    currentSchedule.schedule = currentSchedule.schedule.map((day) => ({
      ...day,
      tickets: day.tickets.map((ticket) =>
        ticket.id === "ticket-1"
          ? { ...ticket, timeSpent: ticket.timeSpent + 10 }
          : ticket,
      ),
    }));

    await act(async () => {
      eventSource.dispatch("ticket_updated", {
        type: "ticket_updated",
        ticketId: "ticket-1",
        data: { timeSpent: 40 },
        timestamp: new Date().toISOString(),
      });
    });

    await waitFor(() =>
      expect(fetchSpy).toHaveBeenCalledWith(
        expect.objectContaining({
          path: apiPaths.workerSchedule,
        }),
      ),
    );

    await waitFor(() =>
      expect(
        screen.getByTestId("worker-schedule-time-tracker"),
      ).toHaveTextContent("40 min"),
    );
  });
});


