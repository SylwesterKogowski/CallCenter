import "@testing-library/jest-dom";
import { act, fireEvent, render, screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

import type {
  ManagerMonitoringResponse,
  TriggerAutoAssignmentResponse,
} from "~/api/manager";
import * as http from "~/api/http";
import { apiPaths } from "~/api/http";

import { ManagerMonitoring } from "../ManagerMonitoring";
import { getTodayDate } from "../utils";

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
    } as MessageEvent<string>;

    if (type === "message") {
      this.onmessage?.(event as MessageEvent);
    }

    this.listeners.get(type)?.forEach((listener) => listener(event));
  }
}

const createQueryClient = () =>
  new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  });

const baseMonitoringResponse: ManagerMonitoringResponse = {
  date: "2025-01-15",
  summary: {
    totalTickets: 120,
    totalWorkers: 25,
    totalQueues: 6,
    averageWorkload: 72,
    averageResolutionTime: 48,
    waitingTicketsTotal: 30,
    inProgressTicketsTotal: 20,
    completedTicketsTotal: 70,
  },
  workerStats: [
    {
      workerId: "worker-1",
      workerLogin: "jan.kowalski",
      ticketsCount: 12,
      timeSpent: 420,
      timePlanned: 480,
      workloadLevel: "normal",
      efficiency: 82,
      categories: ["Sprzedaż", "Wsparcie"],
      completedTickets: 8,
      inProgressTickets: 3,
      waitingTickets: 1,
    },
    {
      workerId: "worker-2",
      workerLogin: "anna.nowak",
      ticketsCount: 15,
      timeSpent: 510,
      timePlanned: 480,
      workloadLevel: "high",
      efficiency: 91,
      categories: ["Sprzedaż"],
      completedTickets: 10,
      inProgressTickets: 4,
      waitingTickets: 1,
    },
  ],
  queueStats: [
    {
      queueId: "queue-sales",
      queueName: "Sprzedaż",
      waitingTickets: 15,
      inProgressTickets: 5,
      completedTickets: 40,
      totalTickets: 60,
      averageResolutionTime: 35,
      assignedWorkers: 10,
    },
    {
      queueId: "queue-support",
      queueName: "Wsparcie techniczne",
      waitingTickets: 10,
      inProgressTickets: 6,
      completedTickets: 25,
      totalTickets: 41,
      averageResolutionTime: 55,
      assignedWorkers: 8,
    },
  ],
  autoAssignmentSettings: {
    enabled: true,
    lastRun: "2025-01-15T10:00:00Z",
    ticketsAssigned: 18,
    settings: {
      considerEfficiency: true,
      considerAvailability: true,
      maxTicketsPerWorker: 12,
    },
  },
};

const renderMonitoring = (response: ManagerMonitoringResponse = baseMonitoringResponse) => {
  const queryClient = createQueryClient();
  const fetchSpy = vi
    .spyOn(http, "apiFetch")
    .mockImplementation(async (options): Promise<unknown> => {
      if (options.path === apiPaths.managerMonitoring) {
        return response;
      }
      throw new Error(`Unhandled API call: ${options.path} (${options.method ?? "GET"})`);
    });

  const view = render(
    <QueryClientProvider client={queryClient}>
      <ManagerMonitoring managerId="manager-123" />
    </QueryClientProvider>,
  );

  return { fetchSpy, view };
};

describe("ManagerMonitoring", () => {
  beforeEach(() => {
    vi.restoreAllMocks();
    MockEventSource.instances = [];
    vi.stubGlobal("EventSource", MockEventSource);
  });

  afterEach(() => {
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
  });

  it("renders monitoring summary, worker stats and queue stats", async () => {
    const { fetchSpy } = renderMonitoring();

    expect(await screen.findByText(/Podsumowanie systemu/i)).toBeInTheDocument();
    expect(screen.getByText(/Łącznie ticketów/i).nextSibling).toHaveTextContent("120");
    expect(screen.getByText(/Aktywni pracownicy/i).nextSibling).toHaveTextContent("25");

    expect(await screen.findByTestId("worker-card-worker-1")).toBeInTheDocument();
    expect(await screen.findByTestId("queue-card-queue-sales")).toBeInTheDocument();

    await waitFor(() => expect(fetchSpy).toHaveBeenCalledTimes(1));
  });

  it("allows changing date and fetches monitoring data for the new day", async () => {
    const firstResponse = { ...baseMonitoringResponse };
    const secondResponse: ManagerMonitoringResponse = {
      ...baseMonitoringResponse,
      date: "2025-01-14",
      summary: {
        ...baseMonitoringResponse.summary,
        totalTickets: 98,
      },
    };

    const fetchSpy = vi.spyOn(http, "apiFetch").mockImplementation(async (options) => {
      if (options.path === apiPaths.managerMonitoring) {
        const params = options.params as { date?: string } | undefined;
        if (params?.date === "2025-01-14") {
          return secondResponse;
        }
        return firstResponse;
      }
      throw new Error(`Unhandled API call: ${options.path}`);
    });

    render(
      <QueryClientProvider client={createQueryClient()}>
        <ManagerMonitoring managerId="manager-123" />
      </QueryClientProvider>,
    );

    const dateInput = await screen.findByLabelText(/Wybierz dzień monitoringu/i);
    fireEvent.change(dateInput, { target: { value: "2025-01-14" } });

    await waitFor(() => {
      const summaryLabel = screen
        .getAllByText(/Łącznie ticketów/i)
        .find((element) => element.tagName === "P");
      expect(summaryLabel).toBeDefined();
      expect(summaryLabel?.nextElementSibling).toHaveTextContent("98");
    });

    expect(fetchSpy).toHaveBeenCalledWith(
      expect.objectContaining({
        path: apiPaths.managerMonitoring,
        params: expect.objectContaining({ date: "2025-01-14" }),
      }),
    );
  });

  it("toggles auto assignment settings", async () => {
    const fetchSpy = vi.spyOn(http, "apiFetch").mockImplementation(async (options) => {
      if (options.path === apiPaths.managerMonitoring) {
        return baseMonitoringResponse;
      }
      if (options.path === apiPaths.managerAutoAssignment && options.method === "PUT") {
        return {
          autoAssignmentSettings: {
            ...baseMonitoringResponse.autoAssignmentSettings,
            enabled: false,
          },
          updatedAt: "2025-01-15T11:00:00Z",
        };
      }
      throw new Error(`Unhandled API call: ${options.path} (${options.method ?? "GET"})`);
    });

    render(
      <QueryClientProvider client={createQueryClient()}>
        <ManagerMonitoring managerId="manager-123" />
      </QueryClientProvider>,
    );

    const toggleButton = await screen.findByRole("button", {
      name: /Automatyczne przypisywanie włączone/i,
    });
    await userEvent.click(toggleButton);

    await waitFor(() =>
      expect(fetchSpy).toHaveBeenCalledWith(
        expect.objectContaining({
          path: apiPaths.managerAutoAssignment,
          method: "PUT",
          body: expect.objectContaining({ enabled: false }),
        }),
      ),
    );
  });

  it("triggers auto assignment manually", async () => {
    const triggerResponse: TriggerAutoAssignmentResponse = {
      message: "Automatyczne przypisywanie zostało uruchomione",
      ticketsAssigned: 5,
      assignedTo: [{ workerId: "worker-1", ticketsCount: 3 }],
      completedAt: "2025-01-15T11:05:00Z",
    };

    const fetchSpy = vi.spyOn(http, "apiFetch").mockImplementation(async (options) => {
      if (options.path === apiPaths.managerMonitoring) {
        return baseMonitoringResponse;
      }
      if (options.path === apiPaths.managerAutoAssignmentTrigger && options.method === "POST") {
        return triggerResponse;
      }
      throw new Error(`Unhandled API call: ${options.path} (${options.method ?? "GET"})`);
    });

    render(
      <QueryClientProvider client={createQueryClient()}>
        <ManagerMonitoring managerId="manager-123" />
      </QueryClientProvider>,
    );

    const triggerButton = await screen.findByRole("button", {
      name: /Ręcznie uruchom przypisywanie/i,
    });
    await userEvent.click(triggerButton);

    await waitFor(() =>
      expect(
        screen.getByText(/Automatyczne przypisywanie zostało uruchomione/i),
      ).toBeInTheDocument(),
    );

    await waitFor(() =>
      expect(fetchSpy).toHaveBeenCalledWith(
        expect.objectContaining({
          path: apiPaths.managerAutoAssignmentTrigger,
          method: "POST",
          body: expect.objectContaining({ date: getTodayDate() }),
        }),
      ),
    );
  });

  it("applies SSE worker stats updates", async () => {
    const fetchSpy = vi.spyOn(http, "apiFetch").mockResolvedValue(baseMonitoringResponse);

    render(
      <QueryClientProvider client={createQueryClient()}>
        <ManagerMonitoring managerId="manager-123" />
      </QueryClientProvider>,
    );

    expect((await screen.findAllByText(/jan\.kowalski/i))[0]).toBeInTheDocument();
    await waitFor(() => expect(MockEventSource.instances.length).toBeGreaterThan(0));
    const eventSource = MockEventSource.instances.at(-1)!;

    act(() => {
      eventSource.dispatch("worker_stats_updated", {
        type: "worker_stats_updated",
        data: {
          workerId: "worker-1",
          efficiency: 95,
          ticketsCount: 14,
        },
        timestamp: "2025-01-15T11:10:00Z",
      });
    });

    await waitFor(() => expect(screen.getByText("95%")).toBeInTheDocument());
    const workerCard = await screen.findByTestId("worker-card-worker-1");
    await waitFor(() => {
      const label = within(workerCard).getByText(/Ticketów ogółem/i);
      expect(label.nextElementSibling).toHaveTextContent("14");
    });

    expect(fetchSpy).toHaveBeenCalledTimes(1);
  });

});


