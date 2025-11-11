import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { MemoryRouter } from "react-router";
import { describe, expect, it, beforeEach, afterEach, vi } from "vitest";

import * as http from "~/api/http";
import { apiPaths } from "~/api/http";
import type { CreateTicketResponse } from "~/api/tickets";

import { TicketAddForm } from "../TicketAddForm";

const createQueryClient = () =>
  new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  });

const renderForm = (ui?: React.ReactNode) => {
  const queryClient = createQueryClient();

  const view = render(
    <MemoryRouter initialEntries={["/ticket-add"]}>
      <QueryClientProvider client={queryClient}>
        {ui ?? <TicketAddForm />}
      </QueryClientProvider>
    </MemoryRouter>,
  );

  return { queryClient, view };
};

describe("TicketAddForm", () => {
  const categoriesResponse = {
    categories: [
      {
        id: "cat-support",
        name: "Wsparcie techniczne",
        description: "Pomoc w rozwiązywaniu problemów technicznych",
        defaultResolutionTimeMinutes: 60,
      },
      {
        id: "cat-sales",
        name: "Sprzedaż",
        description: "Zapytania o ofertę i negocjacje",
        defaultResolutionTimeMinutes: 30,
      },
    ],
  };

  beforeEach(() => {
    vi.restoreAllMocks();
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it("renders ticket add form with categories", async () => {
    vi.spyOn(http, "apiFetch").mockResolvedValueOnce(categoriesResponse);

    renderForm();

    expect(await screen.findByText(/Utwórz nowy ticket/i)).toBeInTheDocument();
    expect(await screen.findByText(/Dane kontaktowe klienta/i)).toBeInTheDocument();
    expect(await screen.findByRole("option", { name: /Wsparcie techniczne/i })).toBeInTheDocument();
  });

  it("requires at least one contact field and category", async () => {
    const fetchSpy = vi.spyOn(http, "apiFetch").mockResolvedValue(categoriesResponse);

    renderForm();

    await waitFor(() => expect(fetchSpy).toHaveBeenCalledTimes(1));

    await userEvent.click(screen.getByRole("button", { name: /Utwórz ticket/i }));

    const contactErrors = await screen.findAllByText(
      /Wymagane jest podanie (adresu )?e-?maila lub telefonu/i,
    );
    expect(contactErrors.length).toBeGreaterThanOrEqual(1);
    expect(await screen.findByText(/Kategoria jest wymagana/i)).toBeInTheDocument();
    expect(fetchSpy).toHaveBeenCalledTimes(1);
  });

  it("creates ticket and navigates to chat", async () => {
    const createResponse: CreateTicketResponse = {
      ticket: {
        id: "ticket-123",
        clientId: "client-1",
        categoryId: "cat-sales",
        title: "Brak internetu",
        description: "Od rana nie działa internet w biurze",
        status: "awaiting_response",
        createdAt: "2025-01-01T10:00:00Z",
      },
    };

    const fetchSpy = vi.spyOn(http, "apiFetch");

    fetchSpy.mockResolvedValueOnce(categoriesResponse);
    fetchSpy.mockImplementationOnce(async (options) => {
      expect(options.path).toBe(apiPaths.tickets);
      expect(options.method).toBe("POST");
      expect(options.body).toEqual({
        client: {
          email: "klient@example.com",
        },
        categoryId: "cat-sales",
        title: "Brak internetu",
        description: "Od rana nie działa internet w biurze",
      });
      return createResponse;
    });

    const navigateMock = vi.fn();
    const onTicketCreated = vi.fn();

    renderForm(
      <TicketAddForm
        navigate={navigateMock}
        onTicketCreated={(ticketId, response) => onTicketCreated(ticketId, response)}
      />,
    );

    await screen.findByRole("option", { name: /Sprzedaż/i });

    await userEvent.type(screen.getByLabelText(/E-mail/i), "klient@example.com");
    await userEvent.selectOptions(screen.getByLabelText(/Kategoria/i), "cat-sales");
    await userEvent.type(screen.getByLabelText(/Tytuł/i), "Brak internetu");
    await userEvent.type(
      screen.getByLabelText(/Opis problemu/i),
      "Od rana nie działa internet w biurze",
    );

    await userEvent.click(screen.getByRole("button", { name: /Utwórz ticket/i }));

    await waitFor(() => expect(navigateMock).toHaveBeenCalledWith("/ticket-chat/ticket-123"));
    expect(onTicketCreated).toHaveBeenCalledWith(createResponse.ticket.id, createResponse);
    expect(fetchSpy).toHaveBeenCalledTimes(2);
  });

  it("displays API validation errors from backend", async () => {
    const apiError = new http.ApiError("Validation failed", 400, {
      message: "Walidacja nie powiodła się",
      errors: {
        "client.email": "E-mail jest nieprawidłowy",
        categoryId: "Kategoria jest wymagana",
      },
    });

    const fetchSpy = vi.spyOn(http, "apiFetch");
    fetchSpy.mockResolvedValueOnce(categoriesResponse);
    fetchSpy.mockRejectedValueOnce(apiError);

    renderForm();

    await screen.findByRole("option", { name: /Sprzedaż/i });

    await userEvent.type(screen.getByLabelText(/E-mail/i), "klient@example.com");
    await userEvent.selectOptions(screen.getByLabelText(/Kategoria/i), "cat-sales");

    await userEvent.click(screen.getByRole("button", { name: /Utwórz ticket/i }));

    expect(await screen.findByText(/Walidacja nie powiodła się/i)).toBeInTheDocument();
    expect(await screen.findByText(/E-mail jest nieprawidłowy/i)).toBeInTheDocument();
    expect(await screen.findByText(/Kategoria jest wymagana/i)).toBeInTheDocument();

    expect(fetchSpy).toHaveBeenCalledTimes(2);
  });

  it("allows retrying category fetch when loading fails", async () => {
    const fetchSpy = vi.spyOn(http, "apiFetch");
    fetchSpy.mockRejectedValueOnce(
      new http.ApiError("Server error", 500, { message: "Ups, coś poszło nie tak" }),
    );
    fetchSpy.mockResolvedValueOnce(categoriesResponse);

    renderForm();

    expect(
      await screen.findByText(/Ups, coś poszło nie tak/i),
    ).toBeInTheDocument();

    await userEvent.click(screen.getByRole("button", { name: /Spróbuj ponownie/i }));

    await waitFor(() =>
      expect(screen.getByRole("option", { name: /Wsparcie techniczne/i })).toBeInTheDocument(),
    );

    expect(fetchSpy).toHaveBeenCalledTimes(2);
  });
});


