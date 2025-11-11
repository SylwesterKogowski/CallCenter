import { render, screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { beforeEach, describe, expect, it, vi } from "vitest";

import * as http from "~/api/http";
import { apiPaths } from "~/api/http";

import { WorkerAvailability } from "../WorkerAvailability";

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

const availabilityResponse = {
  availability: [
    {
      date: "2024-01-15",
      timeSlots: [
        {
          id: "slot-1",
          startTime: "09:00",
          endTime: "12:00",
        },
      ],
      totalHours: 3,
    },
    {
      date: "2024-01-16",
      timeSlots: [],
      totalHours: 0,
    },
    {
      date: "2024-01-17",
      timeSlots: [],
      totalHours: 0,
    },
  ],
};

const renderAvailability = () => {
  const queryClient = createQueryClient();
  render(
    <QueryClientProvider client={queryClient}>
      <WorkerAvailability workerId="worker-1" />
    </QueryClientProvider>,
  );
};

describe("WorkerAvailability", () => {
  beforeEach(() => {
    vi.restoreAllMocks();
  });

  const setupFetchMock = () => {
    const fetchSpy = vi.spyOn(http, "apiFetch").mockImplementation(async (options) => {
      if (options.path === apiPaths.workerAvailability && options.method !== "POST") {
        return availabilityResponse;
      }

      if (options.path.startsWith(apiPaths.workerAvailabilityForDate("2024-01-15"))) {
        return {
          date: "2024-01-15",
          timeSlots: [
            {
              id: "slot-1",
              startTime: "09:00",
              endTime: "12:00",
            },
            {
              id: "slot-2",
              startTime: (options.body as { timeSlots: { startTime: string }[] }).timeSlots[1]
                ?.startTime ?? "13:00",
              endTime: (options.body as { timeSlots: { endTime: string }[] }).timeSlots[1]?.endTime ??
                "15:00",
            },
          ],
          totalHours: 5,
          updatedAt: "2024-01-14T12:00:00Z",
        };
      }

      if (
        options.path === apiPaths.workerAvailabilityTimeSlot("2024-01-15", "slot-1") &&
        options.method === "PUT"
      ) {
        return {
          timeSlot: {
            id: "slot-1",
            startTime: (options.body as { startTime: string }).startTime,
            endTime: (options.body as { endTime: string }).endTime,
          },
          updatedAt: "2024-01-15T10:35:00Z",
        };
      }

      if (
        options.path === apiPaths.workerAvailabilityTimeSlot("2024-01-15", "slot-1") &&
        options.method === "DELETE"
      ) {
        return {
          message: "Przedział czasowy został usunięty",
          deletedAt: "2024-01-15T10:40:00Z",
        };
      }

      if (options.path === apiPaths.workerAvailabilityCopy && options.method === "POST") {
        return {
          copied: [
            {
              date: "2024-01-16",
              timeSlots: [
                {
                  id: "slot-copy-1",
                  startTime: "09:00",
                  endTime: "12:00",
                },
              ],
              totalHours: 3,
            },
          ],
          skipped: [],
        };
      }

      throw new Error(`Unhandled API call: ${options.path} (${options.method ?? "GET"})`);
    });

    return fetchSpy;
  };

  it("renders days from availability response", async () => {
    setupFetchMock();
    renderAvailability();

    expect(await screen.findByText(/Nadchodzący tydzień/i)).toBeInTheDocument();
    const firstDayButton = await screen.findByRole("button", { name: /15 stycznia/i });
    expect(firstDayButton).toBeInTheDocument();
    expect(within(firstDayButton).getByText(/^3$/)).toBeInTheDocument();
  });

  it("adds a new time slot and triggers save mutation", async () => {
    const fetchSpy = setupFetchMock();
    renderAvailability();

    const addButton = await screen.findByRole("button", { name: /Dodaj przedział/i });
    await userEvent.click(addButton);

    const startInput = await screen.findByLabelText(/Godzina rozpoczęcia/i);
    const endInput = await screen.findByLabelText(/Godzina zakończenia/i);

    await userEvent.clear(startInput);
    await userEvent.type(startInput, "13:00");
    await userEvent.clear(endInput);
    await userEvent.type(endInput, "15:00");

    const saveButton = await screen.findByRole("button", { name: /Zapisz przedział/i });
    await userEvent.click(saveButton);

    await waitFor(() =>
      expect(
        fetchSpy.mock.calls.some(
          ([options]) =>
            options.path === apiPaths.workerAvailabilityForDate("2024-01-15") &&
            options.method === "POST" &&
            Array.isArray((options.body as { timeSlots?: unknown }).timeSlots),
        ),
      ).toBe(true),
    );

    const firstDayButton = await screen.findByRole("button", { name: /15 stycznia/i });
    expect(within(firstDayButton).getByText(/13:00 - 15:00/)).toBeInTheDocument();
  });

  it("prevents adding overlapping time slots and shows validation message", async () => {
    setupFetchMock();
    renderAvailability();

    const addButton = await screen.findByRole("button", { name: /Dodaj przedział/i });
    await userEvent.click(addButton);

    const startInput = await screen.findByLabelText(/Godzina rozpoczęcia/i);
    const endInput = await screen.findByLabelText(/Godzina zakończenia/i);

    await userEvent.clear(startInput);
    await userEvent.type(startInput, "12:00");
    await userEvent.clear(endInput);
    await userEvent.type(endInput, "10:00");

    const saveButton = await screen.findByRole("button", { name: /Zapisz przedział/i });
    await userEvent.click(saveButton);

    const errorMessages = await screen.findAllByText(
      "Godzina zakończenia musi być późniejsza niż godzina rozpoczęcia.",
    );
    expect(errorMessages.length).toBeGreaterThan(0);
  });

  it("copies availability to another day", async () => {
    const fetchSpy = setupFetchMock();
    renderAvailability();

    const copyButton = await screen.findByRole("button", { name: /Kopiuj dostępność/i });
    await userEvent.click(copyButton);

    const popupContainer = await screen.findByRole("dialog", { name: /Kopiuj dostępność/i });

    const targetCheckbox = within(popupContainer).getByLabelText(/2024-01-16/i);
    await userEvent.click(targetCheckbox);

    const confirmButton = within(popupContainer).getByRole("button", { name: /Kopiuj/ });
    await userEvent.click(confirmButton);

    await waitFor(() =>
      expect(
        fetchSpy.mock.calls.some(
          ([options]) =>
            options.path === apiPaths.workerAvailabilityCopy &&
            options.method === "POST" &&
            (options.body as { targetDates?: string[] }).targetDates?.includes("2024-01-16"),
        ),
      ).toBe(true),
    );

    const dayButton = await screen.findByRole("button", { name: /16 stycznia/i });
    expect(within(dayButton).getByText(/09:00 - 12:00/)).toBeInTheDocument();
  });
});


