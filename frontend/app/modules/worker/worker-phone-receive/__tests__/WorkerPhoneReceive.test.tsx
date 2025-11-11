import "@testing-library/jest-dom";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { afterEach, describe, expect, it, vi } from "vitest";

import * as http from "~/api/http";
import { apiPaths } from "~/api/http";

import { WorkerPhoneReceive } from "../WorkerPhoneReceive";

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

const startCallResponse = {
  callId: "call-456",
  startTime: new Date(Date.now() - 30_000).toISOString(),
  pausedTickets: [
    {
      ticketId: "ticket-789",
      previousStatus: "in_progress",
      newStatus: "waiting",
    },
  ],
};

const ticketSearchResponse = {
  tickets: [
    {
      id: "ticket-001",
      title: "Problem z połączeniem",
      category: { id: "cat-1", name: "Wsparcie techniczne" },
      status: "waiting",
      client: { id: "client-1", name: "Jan Kowalski", email: "jan@example.com" },
      createdAt: "2024-01-15T09:00:00Z",
      timeSpent: 15,
    },
    {
      id: "ticket-002",
      title: "Aktualizacja oprogramowania",
      category: { id: "cat-2", name: "Aktualizacje" },
      status: "in_progress",
      client: { id: "client-2", name: "Anna Nowak" },
      createdAt: "2024-01-15T08:00:00Z",
      timeSpent: 45,
    },
  ],
  total: 2,
};

const clientSearchResponse = {
  clients: [
    {
      id: "client-123",
      name: "Nowy Klient",
      email: "nowy@example.com",
      phone: "+48123456789",
    },
  ],
  total: 1,
};

const createTicketResponse = {
  ticket: {
    id: "ticket-999",
    title: "Nowy problem",
    category: { id: "cat-1", name: "Wsparcie techniczne" },
    status: "waiting",
    client: {
      id: "client-123",
      name: "Nowy Klient",
      email: "nowy@example.com",
      phone: "+48123456789",
    },
    createdAt: "2024-01-15T10:35:00Z",
    timeSpent: 0,
  },
};

const noteResponse = {
  note: {
    id: "note-123",
    content: "Testowa notatka",
    createdAt: "2024-01-15T10:40:00Z",
    createdBy: "worker-123",
  },
};

const endCallResponse = {
  call: {
    id: "call-456",
    ticketId: "ticket-001",
    duration: 45,
    startTime: "2024-01-15T10:30:00Z",
    endTime: "2024-01-15T10:30:45Z",
  },
  ticket: {
    id: "ticket-001",
    status: "in_progress",
    timeSpent: 25,
    scheduledDate: "2024-01-15",
    updatedAt: "2024-01-15T10:40:00Z",
  },
};

const renderPhoneReceive = () => {
  const queryClient = createQueryClient();
  return render(
    <QueryClientProvider client={queryClient}>
      <WorkerPhoneReceive workerId="worker-123" previousActiveTicket={null} />
    </QueryClientProvider>,
  );
};

describe("WorkerPhoneReceive", () => {
  afterEach(() => {
    vi.restoreAllMocks();
  });

  it("starts phone call when opening modal", async () => {
    const fetchSpy = vi.spyOn(http, "apiFetch").mockImplementation(async (options) => {
      if (options.path === apiPaths.workerPhoneReceive && options.method === "POST") {
        return startCallResponse;
      }

      if (options.path === apiPaths.workerTicketsSearch) {
        return ticketSearchResponse;
      }

      if (options.path === apiPaths.workerPhoneEnd && options.method === "POST") {
        return endCallResponse;
      }

      throw new Error(`Unhandled API call: ${options.path}`);
    });

    renderPhoneReceive();
    const openButton = await screen.findByRole("button", { name: /Odbieram telefon/i });
    await userEvent.click(openButton);

    await waitFor(() => expect(fetchSpy).toHaveBeenCalled());

    expect(await screen.findByText(/Wstrzymane tickety/i)).toBeInTheDocument();

    expect(
      fetchSpy.mock.calls.some(
        ([options]) =>
          options.path === apiPaths.workerPhoneReceive && (options.method ?? "GET") === "POST",
      ),
    ).toBe(true);
  });

  it("allows selecting existing ticket and ending call", async () => {
    const noteRequests: Array<Parameters<typeof http.apiFetch>[0]> = [];
    const fetchSpy = vi.spyOn(http, "apiFetch").mockImplementation(async (options) => {
      if (options.path === apiPaths.workerPhoneReceive && options.method === "POST") {
        return startCallResponse;
      }

      if (options.path === apiPaths.workerTicketsSearch) {
        return ticketSearchResponse;
      }

      if (
        options.path === apiPaths.workerTicketNotes("ticket-001") &&
        options.method === "POST"
      ) {
        noteRequests.push(options);
        return noteResponse;
      }

      if (options.path === apiPaths.workerPhoneEnd && options.method === "POST") {
        return endCallResponse;
      }

      throw new Error(`Unhandled API call: ${options.path} (${options.method ?? "GET"})`);
    });

    renderPhoneReceive();

    const openButton = await screen.findByRole("button", { name: /Odbieram telefon/i });
    await userEvent.click(openButton);

    const ticketButton = await screen.findByRole("button", {
      name: /Problem z połączeniem/i,
    });
    await userEvent.click(ticketButton);

    expect(await screen.findByText(/Wybrany ticket/i)).toBeInTheDocument();

    const notesTextarea = await screen.findByLabelText(/Notatki do połączenia/i);
    await userEvent.type(notesTextarea, "Testowa notatka");

    const saveNoteButton = await screen.findByRole("button", { name: /Zapisz notatkę/i });
    await userEvent.click(saveNoteButton);

    await waitFor(() => expect(noteRequests.length).toBeGreaterThan(0));

    const endCallButton = await screen.findByRole("button", { name: /Zakończyłem połączenie/i });
    await waitFor(() => expect(endCallButton).not.toBeDisabled(), { timeout: 6000 });
    await userEvent.click(endCallButton);

    await waitFor(() =>
      expect(
        fetchSpy.mock.calls.some(
          ([options]) =>
            options.path === apiPaths.workerPhoneEnd &&
            options.method === "POST" &&
            (options.body as { ticketId?: string | null }).ticketId === "ticket-001",
        ),
      ).toBe(true),
    );
  });

  it("creates new ticket, saves note and ends call", async () => {
    const createdTicketRequests: Array<Parameters<typeof http.apiFetch>[0]> = [];
    const noteRequests: Array<Parameters<typeof http.apiFetch>[0]> = [];
    const fetchSpy = vi.spyOn(http, "apiFetch").mockImplementation(async (options) => {
      if (options.path === apiPaths.workerPhoneReceive && options.method === "POST") {
        return startCallResponse;
      }

      if (options.path === apiPaths.workerTicketsSearch) {
        return ticketSearchResponse;
      }

      if (options.path === apiPaths.workerClientsSearch) {
        return clientSearchResponse;
      }

      if (options.path === apiPaths.workerTickets && options.method === "POST") {
        createdTicketRequests.push(options);
        return createTicketResponse;
      }

      if (
        options.path === apiPaths.workerTicketNotes("ticket-999") &&
        options.method === "POST"
      ) {
        noteRequests.push(options);
        return noteResponse;
      }

      if (options.path === apiPaths.workerPhoneEnd && options.method === "POST") {
        return {
          ...endCallResponse,
          call: {
            ...endCallResponse.call,
            ticketId: "ticket-999",
          },
        };
      }

      throw new Error(`Unhandled API call: ${options.path} (${options.method ?? "GET"})`);
    });

    renderPhoneReceive();

    const openButton = await screen.findByRole("button", { name: /Odbieram telefon/i });
    await userEvent.click(openButton);

    const createTabButton = await screen.findByRole("button", { name: /Utwórz ticket/i });
    await userEvent.click(createTabButton);

    const titleInput = await screen.findByLabelText(/Tytuł/i);
    await userEvent.type(titleInput, "Nowy problem");

    const categoryInput = await screen.findByLabelText(/Kategoria/i);
    await userEvent.type(categoryInput, "cat-1");

    const clientSearchInput = await screen.findByLabelText(/Szukaj klienta/i);
    await userEvent.type(clientSearchInput, "Nowy Klient");
    const clientResult = await screen.findByRole("button", { name: /Nowy Klient/i });
    await userEvent.click(clientResult);

    const createButtons = await screen.findAllByRole("button", { name: /Utwórz ticket/i });
    const submitCreateButton =
      createButtons.find((button) => button.getAttribute("type") === "submit") ??
      createButtons[createButtons.length - 1];
    await userEvent.click(submitCreateButton);

    await waitFor(() => expect(createdTicketRequests.length).toBeGreaterThan(0));

    const notesTextarea = await screen.findByLabelText(/Notatki do połączenia/i);
    await userEvent.type(notesTextarea, "Testowa notatka dla nowego ticketa");

    const saveNoteButton = await screen.findByRole("button", { name: /Zapisz notatkę/i });
    await userEvent.click(saveNoteButton);

    await waitFor(() => expect(noteRequests.length).toBeGreaterThan(0));

    const endCallButton = await screen.findByRole("button", { name: /Zakończyłem połączenie/i });
    await waitFor(() => expect(endCallButton).not.toBeDisabled(), { timeout: 6000 });
    await userEvent.click(endCallButton);

    await waitFor(() =>
      expect(
        fetchSpy.mock.calls.some(
          ([options]) =>
            options.path === apiPaths.workerPhoneEnd &&
            options.method === "POST" &&
            (options.body as { ticketId?: string | null }).ticketId === "ticket-999",
        ),
      ).toBe(true),
    );
  });
});


