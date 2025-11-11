import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { describe, expect, it, beforeEach, vi } from "vitest";

import * as http from "~/api/http";
import { apiPaths } from "~/api/http";

import { TicketPlanning } from "../TicketPlanning";

const createQueryClient = () =>
  new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
      },
      mutations: {
        retry: false,
      },
    },
  });

const renderPlanning = () => {
  const queryClient = createQueryClient();
  const view = render(
    <QueryClientProvider client={queryClient}>
      <TicketPlanning workerId="worker-1" />
    </QueryClientProvider>,
  );

  return { queryClient, view };
};

const backlogResponse = {
  tickets: [
    {
      id: "ticket-1",
      title: "Problem z połączeniem",
      category: {
        id: "cat-1",
        name: "Wsparcie techniczne",
        description: "Diagnostyka połączeń",
        defaultResolutionTimeMinutes: 60,
        defaultResolutionTime: 60,
      },
      status: "waiting",
      priority: "high",
      client: {
        id: "client-1",
        name: "Jan Kowalski",
      },
      estimatedTime: 60,
      createdAt: "2024-01-14T10:00:00Z",
      scheduledDate: null,
    },
    {
      id: "ticket-2",
      title: "Aktualizacja danych klienta",
      category: {
        id: "cat-2",
        name: "Obsługa klienta",
        description: "Obsługa zmian danych",
        defaultResolutionTimeMinutes: 30,
        defaultResolutionTime: 30,
      },
      status: "waiting",
      priority: "normal",
      client: {
        id: "client-2",
        name: "Anna Nowak",
      },
      estimatedTime: 30,
      createdAt: "2024-01-14T11:00:00Z",
      scheduledDate: null,
    },
  ],
  total: 2,
};

const scheduleResponse = {
  schedule: [
    {
      date: "2024-01-15",
      isAvailable: true,
      availabilityHours: [
        {
          startTime: "09:00",
          endTime: "17:00",
        },
      ],
      tickets: [],
      totalEstimatedTime: 0,
    },
    {
      date: "2024-01-16",
      isAvailable: true,
      availabilityHours: [
        {
          startTime: "09:00",
          endTime: "17:00",
        },
      ],
      tickets: [],
      totalEstimatedTime: 0,
    },
  ],
};

const predictionsResponse = {
  predictions: [
    {
      date: "2024-01-15",
      predictedTicketCount: 5,
      availableTime: 480,
      efficiency: 0.85,
    },
    {
      date: "2024-01-16",
      predictedTicketCount: 3,
      availableTime: 480,
      efficiency: 0.75,
    },
  ],
};

const categoriesResponse = {
  categories: [
    {
      id: "cat-1",
      name: "Wsparcie techniczne",
      description: "Diagnostyka problemów technicznych",
      defaultResolutionTimeMinutes: 60,
    },
    {
      id: "cat-2",
      name: "Obsługa klienta",
      description: "Obsługa wniosków i zmian",
      defaultResolutionTimeMinutes: 30,
    },
  ],
};

describe("TicketPlanning", () => {
  beforeEach(() => {
    vi.restoreAllMocks();
  });

  const setupSuccessfulFetchMock = () => {
    const fetchSpy = vi.spyOn(http, "apiFetch").mockImplementation(async (options) => {
      switch (options.path) {
        case apiPaths.workerTicketsBacklog:
          return backlogResponse;
        case apiPaths.workerScheduleWeek:
          return scheduleResponse;
        case apiPaths.workerSchedulePredictions:
          return predictionsResponse;
        case apiPaths.ticketCategories:
          return categoriesResponse;
        case apiPaths.workerScheduleAssign:
          if (options.method === "DELETE") {
            return { success: true };
          }
          return {
            assignment: {
              ticketId: (options.body as { ticketId: string }).ticketId,
              date: (options.body as { date: string }).date,
              assignedAt: "2024-01-15T10:30:00Z",
            },
          };
        case apiPaths.workerScheduleAutoAssign:
          return {
            assignments: [],
            totalAssigned: 0,
          };
        default:
          throw new Error(`Unhandled API path in mock: ${options.path}`);
      }
    });

    return fetchSpy;
  };

  it("renders backlog tickets and week schedule", async () => {
    setupSuccessfulFetchMock();

    renderPlanning();

    expect(await screen.findByText(/Backlog ticketów/i)).toBeInTheDocument();
    expect(await screen.findByText(/Problem z połączeniem/i)).toBeInTheDocument();
    expect(await screen.findByText(/Aktualizacja danych klienta/i)).toBeInTheDocument();
    expect(await screen.findByText(/Grafik tygodniowy/i)).toBeInTheDocument();
    expect(await screen.findByText(/Przewidywana ilość ticketów/i)).toBeInTheDocument();
  });

  it("assigns ticket to selected day using mutation", async () => {
    const fetchSpy = setupSuccessfulFetchMock();

    renderPlanning();

    const assignButtons = await screen.findAllByRole("button", { name: /Przypisz do 2024-01-15/i });
    expect(assignButtons.length).toBeGreaterThan(0);

    await userEvent.click(assignButtons[0]!);

    await waitFor(() =>
      expect(
        fetchSpy.mock.calls.some(
          ([options]) =>
            options.path === apiPaths.workerScheduleAssign &&
            options.method === "POST" &&
            (options.body as { ticketId?: string }).ticketId === "ticket-1",
        ),
      ).toBe(true),
    );
  });

  it("triggers auto assignment when user confirms action", async () => {
    const fetchSpy = setupSuccessfulFetchMock();
    const confirmSpy = vi.spyOn(window, "confirm").mockReturnValue(true);

    renderPlanning();

    const autoAssignButton = await screen.findByRole("button", {
      name: /Automatyczne przypisanie/i,
    });

    await userEvent.click(autoAssignButton);

    await waitFor(() =>
      expect(
        fetchSpy.mock.calls.some(
          ([options]) =>
            options.path === apiPaths.workerScheduleAutoAssign &&
            options.method === "POST" &&
            (options.body as { weekStartDate?: string }).weekStartDate === "2024-01-15",
        ),
      ).toBe(true),
    );

    expect(confirmSpy).toHaveBeenCalled();
  });
});


